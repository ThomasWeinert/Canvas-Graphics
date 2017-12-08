<?php

namespace Carica\CanvasGraphics\Vectorizer {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\SVG\Appendable;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Utility\Options;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  /**
   * Vectorize an image by creating shapes
   *
   */
  class Primitive implements Appendable {

    public const OPTION_NUMBER_OF_SHAPES = 'number_of_shapes';
    public const OPTION_OPACITY_START = 'opacity_start';
    public const OPTION_OPACITY_ADJUST = 'opacity_adjust';
    public const OPTION_WORKING_SIZE = 'working_size';
    public const OPTION_OUTPUT_SIZE = 'output_size';
    public const OPTION_ITERATION_START_SHAPES = 'shapes_start_random';
    public const OPTION_ITERATION_STOP_MUTATION_FAILURES = 'shapes_mutation_failures';

    public const OPTION_BLUR_FILTER_DEVIATION = 'blur_filter_deviation';

    private static $_optionDefaults = [
      self::OPTION_NUMBER_OF_SHAPES => 1,
      self::OPTION_OPACITY_START => 1.0,
      self::OPTION_WORKING_SIZE => 256,
      self::OPTION_ITERATION_START_SHAPES => 1, //200,
      self::OPTION_ITERATION_STOP_MUTATION_FAILURES => 1, //30
    ];
    private $_options;

    /**
     * @var
     */
    private $_original;


    public function __construct(ImageData $imageData, array $options = []) {
      $this->_original = $imageData;
      $this->_options = new Options(self::$_optionDefaults, $options);
    }

    public function appendTo(Document $svg) {
      $alpha = $this->_options[self::OPTION_OPACITY_START] * 255;
      $numberOfShapes = $this->_options[self::OPTION_NUMBER_OF_SHAPES];
      $startShapeCount = $this->_options[self::OPTION_ITERATION_START_SHAPES];
      $allowedMutationFailures = $this->_options[self::OPTION_ITERATION_STOP_MUTATION_FAILURES];

      // original image data
      $original = $this->_original;
      $width = $original->width;
      $height = $original->height;

      // create an ImageData object for the target
      $target = new ImageData(
        \array_fill(0, $width * $height * 4, 0), $width, $height
      );

      $shapes = [];
      for ($i = 0; $i < $numberOfShapes; $i++) {
        $currentScore = 0;
        $currentShape = NULL;
        // create n Shapes, compare them an keep the best
        for ($j = 0; $j < $startShapeCount; $j++) {
          [$shape, $score] = $this->getScoredShape(
            $original,
            clone $target,
            function () use ($width, $height) {
              new Primitive\Shape\Triangle($width, $height);
            }
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
          }
        }

        // try to improve the shape
        $failureCount = 0;
        while ($allowedMutationFailures > $failureCount) {
          [$shape, $score] = $this->getScoredShape(
            $original,
            clone $target,
            function () use ($width, $height) {
              new Primitive\Shape\Triangle($width, $height);
            },
            $currentShape
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
            $failureCount = 0;
          } else {
            $failureCount++;
          }
        }

        $shapes[] = $currentShape;
      }
    }

    private function getScoredShape(ImageData $original, ImageData $target, \Closure $createShape, Shape $shape = NULL) {
      $shape = $shape ? $shape->mutate() : $createShape();
      $shapeData = $shape->rasterize()->getContext('2d')->getImageData();
      $shapeBox = $shape->getBoundingBox();
      // get color and set on shape
      $shape->setColor($this->getAverageColor($shape, $original, $target));
      // apply shape to target image data (return a copy of target data)
      $test = $this->applyShapeToTarget($shape, $target);
      // compare with original
      $score = $original->getDistance($test);
      return [$shape, $score];
    }
  }
}

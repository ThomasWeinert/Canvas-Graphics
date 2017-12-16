<?php

namespace Carica\CanvasGraphics\Vectorizer {

  use Carica\CanvasGraphics\Canvas\GD\Image;
  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\Color\Palette\ColorThief;
  use Carica\CanvasGraphics\SVG\Appendable;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Utility\Options;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  /**
   * Vectorize an image by creating shapes
   *
   */
  class Primitive implements Appendable {

    public const OPTION_SHAPE_TYPE = 'shape_type';
    public const OPTION_NUMBER_OF_SHAPES = 'number_of_shapes';
    public const OPTION_OPACITY_START = 'opacity_start';
    public const OPTION_OPACITY_ADJUST = 'opacity_adjust';
    public const OPTION_ITERATION_START_SHAPES = 'shapes_start_random';
    public const OPTION_ITERATION_STOP_MUTATION_FAILURES = 'shapes_mutation_failures';

    public const SHAPE_TRIANGLE = 'triangle';
    public const SHAPE_RECTANGLE = 'rectangle';
    public const SHAPE_ELLIPSE = 'ellipse';

    private static $_optionDefaults = [
      self::OPTION_NUMBER_OF_SHAPES => 1,
      self::OPTION_OPACITY_START => 1.0,
      self::OPTION_ITERATION_START_SHAPES => 1, //200,
      self::OPTION_ITERATION_STOP_MUTATION_FAILURES => 1, //30

      self::OPTION_SHAPE_TYPE => self::SHAPE_TRIANGLE
    ];
    private $_options;

    private $_events = [
      'shape' => NULL
    ];

    /**
     * @var
     */
    private $_original;


    public function __construct(ImageData $imageData, array $options = []) {
      $this->_original = $imageData;
      $this->_options = new Options(self::$_optionDefaults, $options);
    }

    public function appendTo(Document $svg): void {
      $alpha = $this->_options[self::OPTION_OPACITY_START];
      $numberOfShapes = $this->_options[self::OPTION_NUMBER_OF_SHAPES];
      $startShapeCount = $this->_options[self::OPTION_ITERATION_START_SHAPES];
      $allowedMutationFailures = $this->_options[self::OPTION_ITERATION_STOP_MUTATION_FAILURES];

      // original image data
      $original = $this->_original;
      $width = $original->width;
      $height = $original->height;

      $palette = \array_values(\iterator_to_array(new ColorThief($original, 2)));

      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;

      /** @var \DOMElement $background */
      $background = $parent->parentNode->insertBefore(
        $document->createElementNS(self::XMLNS_SVG, 'path'),
        $parent
      );
      $background->setAttribute('fill', $palette[0]->toHexString());
      $background->setAttribute('d', sprintf('M0 0h%dv%dH0z', $width, $height));

      $target = Image::create($width, $height);
      $targetContext = $target->getContext('2d');
      $targetContext->fillColor = $palette[0];
      $targetContext->fillRect(0,0, $width, $height);

      $createShape = function() use ($width, $height) {
        switch ($this->_options[self::OPTION_SHAPE_TYPE]) {
        case self::SHAPE_RECTANGLE :
          return new Primitive\Shape\Rectangle($width, $height);
        case self::SHAPE_ELLIPSE :
          return new Primitive\Shape\Ellipse($width, $height);
        case self::SHAPE_TRIANGLE :
        default:
          return new Primitive\Shape\Triangle($width, $height);
        }
      };

      $targetData = $targetContext->getImageData();
      for ($i = 0; $i < $numberOfShapes; $i++) {
        $currentScore = 0;
        $currentShape = NULL;
        $currentTarget = NULL;
        // create n Shapes, compare them an keep the best
        for ($j = 0; $j < $startShapeCount; $j++) {
          [$shape, $score, $targetWithShape] = $this->getScoredShape(
            $original, $targetData, $createShape, NULL, $alpha
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
            $currentTarget = $targetWithShape;
          }
        }

        // try to improve the shape
        $failureCount = 0;
        while ($allowedMutationFailures > $failureCount) {
          [$shape, $score, $targetWithShape] = $this->getScoredShape(
            $original, $targetData, $createShape, $currentShape, $alpha
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
            $currentTarget = $targetWithShape;
            $failureCount = 0;
          } else {
            $failureCount++;
          }
        }

        if (isset($currentShape, $currentTarget)) {
          $svg->append($currentShape);
          $targetData = $currentTarget;
          if (isset($this->_events['shape'])) {
            $this->_events['shape']($currentScore, $svg);
          }
        }
      }
    }

    public function onShape(\Closure $listener) {
      $this->_events['score'] = $listener;
    }

    private function getScoredShape(
      ImageData $original, ImageData $target, \Closure $createShape, Shape $shape = NULL, float $alpha = 1
    ) {
      $shape = $shape ? $shape->mutate() : $createShape();
      // get color and set on shape
      $shape->setColor($this->computeColor($shape, $original, $target, $alpha));
      // apply shape to target image data
      $targetWithShape = $this->applyShape($shape->getColor(), $shape, $target);
      // compare with original and return score
      $score = $this->getComparator()->getScore($original, $targetWithShape);
      return [$shape, $score, $targetWithShape];
    }

    private function applyShape(Color $color, Shape $shape, ImageData $target) {
      $target = clone $target;
      $shape->eachPoint(
        function(int $fi) use ($color, $target) {
          if ($color['alpha'] < 255) {
            $factor = (float)$color['alpha'] / 255.0;
            $target->data[$fi] = $target->data[$fi] * (1 - $factor) + $color->red * $factor;
            $target->data[$fi + 1] = $target->data[$fi] * (1 - $factor) + $color->red * $factor;
            $target->data[$fi + 2] = $target->data[$fi] * (1 - $factor) + $color->red * $factor;
          } else {
            $target->data[$fi] = $color->red;
            $target->data[$fi + 1] = $color->green;
            $target->data[$fi + 2] = $color->blue;
          }
        }
      );
      return $target;
    }

    private function computeColor(Shape $shape, ImageData $original, ImageData $target, float $alpha = 1) {
      $color = [0,0,0, $alpha * 255];
      $count = 0;
      $shape->eachPoint(
        function(int $fi) use (&$color, &$count, $original) {
          $color[0] += $original->data[$fi];
          $color[1] += $original->data[$fi+1];
          $color[2] += $original->data[$fi+2];
          $count++;
        }
      );
      if ($count > 0) {
        return Color::create($color[0] / $count, $color[1] / $count, $color[2] / $count, $alpha * 255);
      }
      return Color::createGray(255, $alpha * 255);
    }

    private function getComparator() {
      return new ImageData\Comparator();
    }
  }
}

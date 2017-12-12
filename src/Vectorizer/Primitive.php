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

    public const OPTION_NUMBER_OF_SHAPES = 'number_of_shapes';
    public const OPTION_OPACITY_START = 'opacity_start';
    public const OPTION_OPACITY_ADJUST = 'opacity_adjust';
    public const OPTION_ITERATION_START_SHAPES = 'shapes_start_random';
    public const OPTION_ITERATION_STOP_MUTATION_FAILURES = 'shapes_mutation_failures';

    public const OPTION_BLUR_FILTER_DEVIATION = 'blur_filter_deviation';

    private static $_optionDefaults = [
      self::OPTION_NUMBER_OF_SHAPES => 1,
      self::OPTION_OPACITY_START => 1.0,
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

      $palette = \array_values(\iterator_to_array(new ColorThief($original, 2)));

      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;

      /** @var \DOMElement $rectNode */
      $rectNode = $parent->appendChild(
        $document->createElementNS(self::XMLNS_SVG, 'rect')
      );
      $rectNode->setAttribute('x', 0);
      $rectNode->setAttribute('y', 0);
      $rectNode->setAttribute('width', $width);
      $rectNode->setAttribute('height', $height);
      $rectNode->setAttribute('fill', $palette[0]->toHexString());



      $target = Image::create($width, $height);
      $targetContext = $target->getContext('2d');
      $targetContext->fillColor = $palette[0];
      $targetContext->fillRect(0,0, $width, $height);
      $targetData = $targetWithShape = $targetContext->getImageData();

      $createShape = function() use ($width, $height) {
        return new Primitive\Shape\Triangle($width, $height);
        //return new Primitive\Shape\Rectangle($width, $height);
      };

      for ($i = 0; $i < $numberOfShapes; $i++) {
        $currentScore = 0;
        $currentShape = NULL;
        // create n Shapes, compare them an keep the best
        for ($j = 0; $j < $startShapeCount; $j++) {
          [$shape, $score, $targetWithShape] = $this->getScoredShape(
            $original, $targetData, $createShape
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
          }
        }

        // try to improve the shape
        $failureCount = 0;
        while ($allowedMutationFailures > $failureCount) {
          [$shape, $score, $targetWithShape] = $this->getScoredShape(
            $original, $targetData, $createShape, $currentShape
          );
          if ($score > $currentScore) {
            $currentScore = $score;
            $currentShape = $shape;
            $failureCount = 0;
          } else {
            $failureCount++;
          }
        }
        $svg->append($currentShape);
        $targetData = $targetWithShape;
      }
    }

    private function getScoredShape(
      ImageData $original, ImageData $target, \Closure $createShape, Shape $shape = NULL
    ) {
      $shape = $shape ? $shape->mutate() : $createShape();
      $shapeData = $shape->rasterize()->getContext('2d')->getImageData();
      $shapeBox = $shape->getBoundingBox();
      // get color and set on shape
      $shape->setColor($this->computeColor($shapeBox, $shapeData, $original, $target));
      // apply shape to target image data
      $targetWithShape = $this->applyShape($shape->getColor(), $shapeBox, $shapeData, $target);
      // compare with original and return score
      $score = $this->getComparator()->getScore($original, $targetWithShape);
      return [$shape, $score, $targetWithShape];
    }

    private function applyShape(Color $color, array $offset, ImageData $shape, ImageData $target) {
      $target = clone $target;

      $sw = $offset['width'];
      $sh = $offset['height'];
      $fw = $target->width;
      $fh = $target->height;

      for ($sy = 0; $sy < $sh; $sy++) {
        $fy = $sy + $offset['top'];
        if ($fy < 0 || $fy >= $fh) { continue; } /* outside of the large canvas (vertically) */

        for ($sx=0; $sx < $sw; $sx++) {
          $fx = $offset['left'] + $sx;
          if ($fx < 0 || $fx >= $fw) { continue; } /* outside of the large canvas (horizontally) */

          $si = 4 * ($sx + $sy*$sw); /* shape (local) index */
          if (!isset($shape->data[$si]) || $shape->data[$si+3] === 0) { continue; } /* only where drawn */

          $fi = 4 * ($fx + $fy * $fw); /* full (global) index */

          // change pixel data in target according to colors values and alpha
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
      }
      return $target;
    }

    private function computeColor(array $offset, ImageData $shape, ImageData $original, ImageData $target, float $alpha = 1) {
      $color = [0,0,0, $alpha * 255];
	    $originalData = $original->data;
      $targetData = $target->data;
      $shapeData = $shape->data;

      $sw = $offset['width'];
      $sh = $offset['height'];
      $fw = $original->width;
      $fh = $original->height;
      $count = 0;

      for ($sy = 0; $sy < $sh; $sy++) {
        $fy = $sy + $offset['top'];
        if ($fy < 0 || $fy >= $fh) { continue; } /* outside of the large canvas (vertically) */

        for ($sx=0; $sx < $sw; $sx++) {
          $fx = $offset['left'] + $sx;
          if ($fx < 0 || $fx >= $fw) { continue; } /* outside of the large canvas (horizontally) */

          $si = 4 * ($sx + $sy*$sw); /* shape (local) index */
          if (!isset($shapeData[$si]) || $shapeData[$si+3] === 0) { continue; } /* only where drawn */

          $fi = 4 * ($fx + $fy * $fw); /* full (global) index */
          $color[0] += $originalData[$fi];
          $color[1] += $originalData[$fi+1];
          $color[2] += $originalData[$fi+2];

          /*
          $color[0] += ($targetData[$fi] - $originalData[$fi]) / $alpha + $originalData[$fi];
          $color[1] += ($targetData[$fi+1] - $originalData[$fi+1]) / $alpha + $originalData[$fi+1];
          $color[2] += ($targetData[$fi+2] - $originalData[$fi+2]) / $alpha + $originalData[$fi+2];
          */
          $count++;
        }
      }
      if ($count > 0) {
        return Color::create($color[0] / $count, $color[1] / $count, $color[2] / $count);
      } else {
        return Color::createGray(255);
      }
    }

    private function getComparator() {
      return new ImageData\Comparator();
    }
  }
}

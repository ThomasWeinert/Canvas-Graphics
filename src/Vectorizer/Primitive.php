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
    public const SHAPE_QUADRILATERAL = 'quadrilateral';
    public const SHAPE_RECTANGLE = 'rectangle';
    public const SHAPE_CIRCLE = 'circle';
    public const SHAPE_ELLIPSE = 'ellipse';
    public const SHAPE_RECTANGLE_ROTATED = 'rectangle_rotated';

    private const SHAPES = [
      self::SHAPE_TRIANGLE => Shape\Triangle::class,
      self::SHAPE_QUADRILATERAL => Shape\Quadrilateral::class,
      self::SHAPE_RECTANGLE => Shape\Rectangle::class,
      self::SHAPE_RECTANGLE_ROTATED => Shape\RotatedRectangle::class,
      self::SHAPE_CIRCLE => Shape\Circle::class,
      self::SHAPE_ELLIPSE => Shape\Ellipse::class
    ];

    private static $_optionDefaults = [
      self::OPTION_NUMBER_OF_SHAPES => 10,
      self::OPTION_ITERATION_START_SHAPES => 20,
      self::OPTION_ITERATION_STOP_MUTATION_FAILURES => 10,
      self::OPTION_SHAPE_TYPE => self::SHAPE_TRIANGLE,

      self::OPTION_OPACITY_START => 1.0,
      self::OPTION_OPACITY_ADJUST => FALSE
    ];
    /**
     * @var Options
     */
    private $_options;

    private $_events = [
      'shape-create' => NULL,
      'shape-added' => NULL
    ];

    /**
     * @var ImageData
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

      if (NULL !== $this->_events['shape-create']) {
        $createShape = $this->_events['shape-create'];
      } else {
        $shapeClass = self::SHAPES[$this->_options[self::OPTION_SHAPE_TYPE]] ?? Shape\Triangle::class;
        $createShape = function (int $width, int $height, int $index) use ($shapeClass) {
          return new $shapeClass($width, $height, $index);
        };
      }

      $targetData = $targetContext->getImageData();
      for ($i = 0; $i < $numberOfShapes; $i++) {
        /**
         * @var float $lastChange
         * @var NULL|Shape $currentShape
         * @var NULL|Shape $shape
         */
        $lastChange = 0;
        $currentShape = NULL;

        // create n Shapes, compare them an keep the best
        for ($j = 0; $j < $startShapeCount; $j++) {
          $shape = $createShape($width, $height, $i);
          $shape->setColor($this->computeColor($shape, $original, $targetData, $alpha));
          $distanceChange = $this->getDistanceChange($shape, $original, $targetData);
          if ($distanceChange < $lastChange) {
            $lastChange = $distanceChange;
            $currentShape = $shape;
          }
        }

        // try to improve the shape
        $failureCount = 0;
        while ($allowedMutationFailures > $failureCount) {
          $shape = $currentShape->mutate();
          $shape->setColor($this->computeColor($shape, $original, $targetData, $alpha));
          $distanceChange = $this->getDistanceChange($shape, $original, $targetData);
          if ($distanceChange < $lastChange) {
            $lastChange = $distanceChange;
            $currentShape = $shape;
            $failureCount = 0;
          } else {
            $failureCount++;
          }
        }

        // optimize alpha
        if ($this->_options[self::OPTION_OPACITY_ADJUST]) {
          $color = $currentShape->getColor();
          $lowerAlpha = $alpha;
          $upperAlpha = 1.0;
          if ($lowerAlpha < $upperAlpha) {
            $lowerAlphaChange = $lastChange;
            $color->alpha = \round($upperAlpha * 255);
            $currentShape->setColor($color);
            $upperAlphaChange = $this->getDistanceChange($currentShape, $original, $targetData);
            while ($upperAlpha - $lowerAlpha > 0.01) {
              echo '<br/>';
              if ($upperAlphaChange < $lowerAlphaChange) {
                $lowerAlpha += ($upperAlpha - $lowerAlpha) / 2;
                $color->alpha = \round($lowerAlpha * 255);
                $currentShape->setColor($color);
                $lowerAlphaChange = $this->getDistanceChange($currentShape, $original, $targetData);
              } else {
                $upperAlpha -= ($upperAlpha - $lowerAlpha) / 2;
                $color->alpha = \round($upperAlpha * 255);
                $currentShape->setColor($color);
                $upperAlphaChange = $this->getDistanceChange($currentShape, $original, $targetData);
              }
            }
          }
        }

        if (NULL !== $currentShape) {
          $svg->append($currentShape);
          $targetData = $this->applyShape($currentShape->getColor(), $currentShape, $targetData);
          if (isset($this->_events['shape-added'])) {
            $this->_events['shape-added'](
              $this->getComparator()->getScore($original, $targetData), $svg
            );
          }
        }
      }
    }

    public function onShapeAdded(\Closure $listener) {
      $this->_events['shape-added'] = $listener;
    }

    public function onShapeCreate(\Closure $listener) {
      $this->_events['shape-create'] = $listener;
    }

    private function getDistanceChange(Shape $shape, ImageData $original, ImageData $target) {
      $distanceChange = 0;
      $count = 0;
      $shapeColor = $shape->getColor();
      $shapePixel = [
        $shapeColor->red, $shapeColor->green, $shapeColor->blue, $shapeColor->alpha
      ];
      $shape->eachPoint(
        function(int $fi) use (&$distanceChange, &$count, $original, $target, $shapeColor, $shapePixel) {
          $count++;
          $originalPixel = $this->getPixel($original->data, $fi);
          $targetPixel = $this->getPixel($target->data, $fi);
          $distanceChange -= $this->getPixelDistance($originalPixel, $targetPixel);

          $pixel = $shapePixel;
          if ($shapeColor->alpha < 255) {
            $pixel = Color::removeAlphaFromColor($shapeColor, $targetPixel);
          }
          $distanceChange += $this->getPixelDistance($originalPixel, $pixel);
        }
      );
      return $distanceChange;
    }

    private function getPixel(array $data, $index): array {
      return [
        $data[$index], $data[$index + 1], $data[$index + 2], $data[$index + 3]
      ];
    }

    public function getPixelDistance($a, $b) {
      return (
        \abs($a[0] - $b[0]) +
        \abs($a[1] - $b[1]) +
        \abs($a[2] - $b[2])
      ) / (255 * 3);
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

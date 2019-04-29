<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive {

  use Carica\CanvasGraphics\Canvas\GD\Image;
  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\SVG\Appendable;

  abstract class Shape implements Appendable {

    /**
     * @var CanvasContext2D
     */
    private $_canvasContext;
    /**
     * @var array
     */
    private $_visibleOffsets;

    /**
     * @var Color
     */
    private $_color;
    /**
     * @var \Carica\CanvasGraphics\Color
     */
    private $_backgroundColor;
    /**
     * @var int
     */
    private $_imageWidth;
    /**
     * @var int
     */
    private $_imageHeight;
    /**
     * @var array
     */
    private $_distanceBuffer = [
      'original' => NULL,
      'target' => NULL,
      'distance' => 0,
    ];

    protected $_minDistance = 5;
    protected $_maxDistance = 20;

    abstract public function render(CanvasContext2D $canvas);

    abstract public function mutate();

    abstract public function getBoundingBox(): array;

    public function __construct(
      int $width, int $height, Color $backgroundColor
    ) {
      $this->_imageWidth = $width;
      $this->_imageHeight = $height;
      $this->_backgroundColor = $backgroundColor;
    }

    public static function createRandomPoint(int $width, int $height): array {
      try {
        return [\random_int(0, $width - 1), \random_int(0, $height - 1)];
      } catch (\Throwable $e) {
        throw new \OutOfRangeException('Invalid width or height');
      }
    }

    public function rasterize(): CanvasContext2D {
      if (NULL === $this->_canvasContext) {
        $box = $this->getBoundingBox();
        $this->_canvasContext = $context =
          Image::create(\max($box['width'], 1), \max($box['height'], 1))->getContext();
        $context->fillColor = [0, 255, 0, 255];
        $context->translate(-$box['left'],-$box['top']);
        $this->render($context);
        $this->_visibleOffsets = NULL;
      }
      return $this->_canvasContext;
    }

    public function eachPoint(\Closure $callback): void {
      if (NULL === $this->_visibleOffsets) {
        $box = $this->getBoundingBox();
        $data = $this->rasterize()->getImageData()->data;
        $fw = $this->_imageWidth;
        $fh = $this->_imageHeight;
        $sw = $box['width'];
        $sh = $box['height'];
        for ($sy = 0; $sy < $sh; $sy++) {
          $fy = $sy + $box['top'];
          if ($fy < 0 || $fy >= $fh) { continue; } /* outside of the large canvas (vertically) */

          for ($sx=0; $sx < $sw; $sx++) {
            $fx = $box['left'] + $sx;
            if ($fx < 0 || $fx >= $fw) { continue; } /* outside of the large canvas (horizontally) */

            $si = 4 * ($sx + ($sy * $sw)); /* shape (local) index */
            if ($data[$si+3] === 0) { continue; } /* only where drawn */

            $fi = 4 * ($fx + $fy * $fw); /* full (global) index */

            $this->_visibleOffsets[] = [$fi, $si];
            $callback($fi, $si);
          }
        }
      } else {
        foreach ($this->_visibleOffsets as $offsets) {
          $callback(...$offsets);
        }
      }
    }

    public function reducePoints(\Closure $callback, $initial = NULL) {
      if (NULL === $this->_visibleOffsets) {
        $distanceChange = $initial;
        $this->eachPoint(
          static function(...$offsets) use (&$distanceChange, $callback) {
            $distanceChange = $callback($distanceChange, ...$offsets);
          }
        );
        return $distanceChange;
      }
      return \array_reduce(
        $this->_visibleOffsets,
        static function($carry, $offsets) use ($callback) {
          return $callback($carry, ...$offsets);
        },
        $initial
      );
    }

    public function getDistanceChange(
      ImageData $original, ImageData $target, bool $useCache = FALSE
    ): float {
      $shapeColor = $this->getColor();
      $shapePixel = [
        $shapeColor->red, $shapeColor->green, $shapeColor->blue, $shapeColor->alpha
      ];
      if (
        $useCache &&
        $this->_distanceBuffer['original'] === $original &&
        $this->_distanceBuffer['target'] === $target
      ) {
        $distanceTarget = $this->_distanceBuffer['distance'];
        $distanceShape = 0;
        $this->eachPoint(
          function(int $fi) use (&$distanceShape, $original, $target, $shapeColor, $shapePixel) {
            $originalPixel = $this->getPixel($original->data, $fi, $this->_backgroundColor);
            $pixel = $shapePixel;
            if ($shapeColor->alpha < 255) {
              $targetPixel = $this->getPixel($target->data, $fi, $this->_backgroundColor);
              $pixel = Color::removeAlphaFromColor($shapeColor, $targetPixel);
            }
            $distanceShape += $this->getPixelDistance($originalPixel, $pixel);
          }
        );
      } else {
        $distanceTarget = 0;
        $distanceShape = 0;
        $this->eachPoint(
          function(int $fi) use (&$distanceTarget, &$distanceShape, $original, $target, $shapeColor, $shapePixel) {
            $originalPixel = $this->getPixel($original->data, $fi, $this->_backgroundColor);
            $targetPixel = $this->getPixel($target->data, $fi, $this->_backgroundColor);
            $distanceTarget += $this->getPixelDistance($originalPixel, $targetPixel);

            $pixel = $shapePixel;
            if ($shapeColor->alpha < 255) {
              $pixel = Color::removeAlphaFromColor($shapeColor, $targetPixel);
            }
            $distanceShape += $this->getPixelDistance($originalPixel, $pixel);
          }
        );
        $this->_distanceBuffer = [
          'original' => $original,
          'target' => $target,
          'distance' => $distanceTarget
        ];
      }
      return -$distanceTarget + $distanceShape;
    }

    private function getPixel(array $data, $index, $backgroundColor) {
      $pixel = [
        $data[$index], $data[$index + 1], $data[$index + 2], $data[$index + 3]
      ];
      if ($pixel[3] < 255) {
        return Color::removeAlphaFromColor($pixel, $backgroundColor);
      }
      return $pixel;
    }

    public function getPixelDistance($a, $b) {
      $redness = (float)($a[0] + $b[0]) / 2;
      $deltaRed = (float)($a[0] - $b[0]) ** 2;
      $deltaGreen = (float)($a[1] - $b[1]) ** 2;
      $deltaBlue = (float)($a[2] - $b[2]) ** 2;
      return
        (2 + $redness / 256) * $deltaRed +
        4 * $deltaGreen +
        (2 + (255 - $redness) / 256) * $deltaBlue;
    }

    public function __clone() {
      $this->_canvasContext = NULL;
      $this->_visibleOffsets = NULL;
      $this->_distanceBuffer = [
        'original' => NULL,
        'target' => NULL,
        'distance' => 0
      ];
    }

    public function setColor(Color $color): void {
      $this->_color = clone $color;
    }

    public function getColor(): Color {
      if (NULL === $this->_color) {
        $this->_color = Color::createGray(0);
      }
      return $this->_color;
    }

    protected function isOutsideImage($boundingBox): bool {
      return (
        $boundingBox['bottom'] < 0 ||
        $boundingBox['right'] < 0 ||
        $boundingBox['top'] >= $this->_imageHeight ||
        $boundingBox['left'] >= $this->_imageWidth
      );
    }
  }
}



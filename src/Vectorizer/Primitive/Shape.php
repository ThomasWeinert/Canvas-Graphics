<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive {

  use Carica\CanvasGraphics\Canvas\GD\Image;
  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
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
    private $_visibleOffset;

    /**
     * @var Color
     */
    private $_color;
    /**
     * @var int
     */
    private $_imageWidth;
    /**
     * @var int
     */
    private $_imageHeight;

    abstract public function render(CanvasContext2D $canvas);

    abstract public function mutate();

    abstract public function getBoundingBox(): array;

    public function __construct(int $width, int $height) {
      $this->_imageWidth = $width;
      $this->_imageHeight = $height;
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
        $this->_visibleOffset = NULL;
      }
      return $this->_canvasContext;
    }

    public function eachPoint(\Closure $callback) {
      if (NULL === $this->_visibleOffset) {
        $box = $this->getBoundingBox();
        $data = $this->rasterize()->getImageData()->data;
        $sw = $box['width'];
        $sh = $box['height'];
        $fw = $this->_imageWidth;
        $fh = $this->_imageHeight;
        for ($sy = 0; $sy < $sh; $sy++) {
          $fy = $sy + $box['top'];
          if ($fy < 0 || $fy >= $fh) { continue; } /* outside of the large canvas (vertically) */

          for ($sx=0; $sx < $sw; $sx++) {
            $fx = $box['left'] + $sx;
            if ($fx < 0 || $fx >= $fw) { continue; } /* outside of the large canvas (horizontally) */

            $si = 4 * ($sx + $sy*$sw); /* shape (local) index */
            if (!isset($data[$si]) || $data[$si+3] === 0) { continue; } /* only where drawn */

            $fi = 4 * ($fx + $fy * $fw); /* full (global) index */

            $this->_visibleOffset[] = [$fi, $si];
            $callback($fi, $si);
          }
        }
      } else {
        foreach ($this->_visibleOffset as $offsets) {
          $callback(...$offsets);
        }
      }
    }

    public function __clone() {
      $this->_canvasContext = NULL;
      $this->_visibleOffset = NULL;
    }

    public function setColor(Color $color) {
      $this->_color = $color;
    }

    public function getColor() {
      if (NULL === $this->_color) {
        $this->_color = Color::createGray(0);
      }
      return $this->_color;
    }
  }
}



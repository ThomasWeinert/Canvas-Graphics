<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive {

  use Carica\CanvasGraphics\Canvas\GD\Image;
  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\SVG\Appendable;

  abstract class Shape implements Appendable {

    private $_canvas;
    private $_color;

    abstract public function render(CanvasContext2D $canvas);

    abstract public function mutate();

    abstract public function getBoundingBox(): array;

    public static function createRandomPoint(int $width, int $height): array {
      try {
        return [\random_int(0, $width - 1), \random_int(0, $height - 1)];
      } catch (\Throwable $e) {
        throw new \OutOfRangeException('Invalid width or height');
      }
    }

    public function rasterize(): Image {
      if (NULL === $this->_canvas) {
        $box = $this->getBoundingBox();
        $this->_canvas = $canvas = Image::create(\max($box['width'], 1), \max($box['height'], 1));
        $context = $canvas->getContext();
        $context->fillColor = [255, 0, 0, 255];
        $context->translate(-$box['left'],-$box['top']);
        $this->render($context);
      }
      return $this->_canvas;
    }

    public function __clone() {
      $this->_canvas = NULL;
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



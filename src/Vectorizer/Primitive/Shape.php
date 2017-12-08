<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive {

  use Carica\CanvasGraphics\Canvas\GD\Image;
  use Carica\CanvasGraphics\Canvas\CanvasContext2D;

  abstract class Shape {

    private $_canvas;

    abstract public function render(CanvasContext2D $canvas);

    abstract public function mutate();

    abstract public function getBoundingBox(): array;

    public static function createRandomPoint(int $width, int $height): array {
      return [random_int(0, $width - 1), random_int(0, $height - 1)];
    }

    public function rasterize(): Image {
      if (NULL === $this->_canvas) {
        $box = $this->getBoundingBox();
        $this->_canvas = $canvas = Image::create($box['width'], $box['height']);
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
  }
}



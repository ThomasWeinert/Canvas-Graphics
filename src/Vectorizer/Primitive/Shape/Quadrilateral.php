<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  class Quadrilateral extends Polygon {

    public function __construct(int $width, int $height) {
      parent::__construct($width, $height, 4);
    }
  }
}



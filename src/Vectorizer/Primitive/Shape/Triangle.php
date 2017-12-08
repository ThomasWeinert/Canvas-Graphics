<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  class Triangle extends Polygon {

    public function __construct(int $width, int $height) {
      parent::__construct($width, $height, 3);
    }
  }
}



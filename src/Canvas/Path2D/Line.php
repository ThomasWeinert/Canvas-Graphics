<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  class Line extends Straight {

    public function __construct($x, $y) {
      parent::__construct('L', $x, $y);
    }
  }
}

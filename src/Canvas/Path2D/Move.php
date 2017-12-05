<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  class Move extends Straight {

    public function __construct($x, $y) {
      parent::__construct('M', $x, $y);
    }
  }
}

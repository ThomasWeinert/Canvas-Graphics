<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  abstract class Straight extends Points {

    public function __construct(string $type, int $x, int $y) {
      parent::__construct($type, [$x, $y]);
    }

    public function getTargetPoint() {
      return $this[0];
    }
  }
}

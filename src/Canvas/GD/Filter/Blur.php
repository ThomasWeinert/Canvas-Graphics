<?php

namespace Carica\CanvasGraphics\Canvas\GD\Filter {

  use Carica\CanvasGraphics\Canvas\GD\Filter;

  class Blur implements Filter {

    private $_factor;

    public function __construct(int $blurFactor = 3) {
      $this->_factor = $blurFactor;
    }

    public function applyTo(&$image): void {
      if ($this->_factor < 1) {
        return;
      }
      for($i = 0; $i < $this->_factor; $i++) {
        \imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
      }
    }
  }
}


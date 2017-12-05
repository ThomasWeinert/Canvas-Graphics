<?php

namespace Carica\CanvasGraphics\Filter {

  use Carica\CanvasGraphics\Filter;

  class Blur implements Filter {

    private $_factor;

    public function __construct(int $blurFactor = 3) {
      $this->_factor = $blurFactor;
    }

    public function apply(&$image): void {
      if ($this->_factor < 1) {
        return;
      }
      for($i = 0; $i < $this->_factor; $i++) {
        \imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
      }
    }
  }
}


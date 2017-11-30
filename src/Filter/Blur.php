<?php

namespace Carica\BitmapToSVG\Filter {

  class Blur {

    private $_factor;

    public function __construct(int $blurFactor = 3) {
      $this->_factor = $blurFactor;
    }

    public function apply(&$image) {
      if ($this->_factor < 1) {
        return;
      }
      for($i = 0; $i < $this->_factor; $i++) {
        \imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
      }
    }
  }
}


<?php

namespace Carica\CanvasGraphics\Canvas\GD\Filter {

  use Carica\CanvasGraphics\Canvas\GD\Filter;

  class LimitSize implements Filter {

    private $_width;
    private $_height;

    public function __construct(int $width, int $height) {
      $this->_width = max($width, 1);
      $this->_height = max($height, 1);
    }

    public function applyTo(&$image): void {
      $originalWidth = imagesx($image);
      $originalHeight = imagesy($image);
      if ($originalWidth <= $this->_width && $originalHeight <=$this->_height) {
        return;
      }
      $divWidth = $originalWidth / $this->_width;
      $divHeight = $originalHeight / $this->_height;
      if ($divWidth >= $divHeight) {
        $newWidth = $this->_width;
        $newHeight = round($originalHeight / $divWidth);
      } else {
        $newHeight = $this->_height;
        $newWidth = round($originalWidth / $divHeight);
      }
      $image = \imagescale($image, $newWidth, $newHeight);
    }
  }
}


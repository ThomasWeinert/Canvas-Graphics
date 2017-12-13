<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  class Rectangle implements Segment {

    private $_x;
    private $_y;
    private $_width;
    private $_height;

    public function __construct(int $x, int $y, int $width, int $height) {
      $this->_x = $x;
      $this->_y = $y;
      $this->_width = $width;
      $this->_height = $height;
    }

    public function getX() {
      return $this->_x;
    }

    public function getY() {
      return $this->_y;
    }

    public function getWidth() {
      return $this->_width;
    }

    public function getHeight() {
      return $this->_height;
    }
  }
}

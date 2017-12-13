<?php

namespace Carica\CanvasGraphics\Canvas\Path2D {

  class Ellipse implements Segment {

    private $_centerX;
    private $_centerY;
    private $_radiusX;
    private $_radiusY;

    public function __construct(int $centerX, int $centerY, int $radiusX, int $radiusY) {
      $this->_centerX = $centerX;
      $this->_centerY = $centerY;
      $this->_radiusX = $radiusX;
      $this->_radiusY = $radiusY;
    }

    public function getCenterX() {
      return $this->_centerX;
    }
    public function getCenterY() {
      return $this->_centerY;
    }
    public function getRadiusX() {
      return $this->_radiusX;
    }
    public function getRadiusY() {
      return $this->_radiusY;
    }
  }
}

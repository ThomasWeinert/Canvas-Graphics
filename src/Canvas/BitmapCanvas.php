<?php

namespace Carica\CanvasGraphics\Canvas {

  use Carica\CanvasGraphics\Canvas\Context2D\GDCanvasContext;

  class BitmapCanvas {

    private $_width;
    private $_height;

    private $_contexts = [];

    public function __construct(int $width, int $height) {
      $this->_width = $width;
      $this->_height = $height;
    }

    public function toBlob(string $type = 'image/png', float $encoderOptions = NULL) {
      return $this->getContext()->toBlob($type, $encoderOptions);
    }

    public function getContext($type = '2d') {
      if ($type !== '2d') {
        return new \LogicException('Only 2d canvas context is supported at the moment.');
      }
      if (isset($this->_contexts[$type])) {
        return $this->_contexts[$type];
      }
      return $this->_contexts[$type] = new GDCanvasContext($this->_width, $this->_height);
    }
  }
}

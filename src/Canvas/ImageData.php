<?php

namespace Carica\CanvasGraphics\Canvas {

  class ImageData {

    public $data;
    public $width;
    public $height;

    public function __construct(array $data, int $width, int $height) {
      $this->data = $data;
      $this->width = $width;
      $this->height = $height;
    }
  }
}

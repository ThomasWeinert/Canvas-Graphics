<?php

namespace Carica\CanvasGraphics\Canvas\GD\Filter {

  use Carica\CanvasGraphics\Canvas\GD\Filter;
  use Carica\CanvasGraphics\Color;

  class BackgroundColor implements Filter {

    private $_color;

    public function __construct(Color $color) {
      $this->_color = $color;
    }

    public function applyTo(&$image): void {
      $width = \imagesx($image);
      $height = \imagesy($image);
      $newImage = \imagecreatetruecolor($width, $height);
      $backgroundColor = imagecolorallocate(
        $newImage, $this->_color->red, $this->_color->green, $this->_color->blue
      );
      \imagefilledrectangle(
        $newImage, 0, 0, $width, $height, $backgroundColor
      );
      \imagecopy(
        $newImage, $image, 0,0, 0, 0, $width, $height
      );
      $image = $newImage;
    }
  }
}


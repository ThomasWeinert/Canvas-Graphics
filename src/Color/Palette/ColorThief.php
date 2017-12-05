<?php

namespace Carica\CanvasGraphics\Color\Palette {

  use Carica\CanvasGraphics\Color;
  use ColorThief\ColorThief as ColorThiefLibrary;

  class ColorThief extends Color\Palette {

    private $_numberOfColors;
    private $_image;

    public function __construct($image, int $numberOfColors) {
      $this->_image = $image;
      $this->_numberOfColors = $numberOfColors;
    }

    public function generate(): array {
      return \array_merge(
        [
          Color::createGray(0),
          Color::createGray(255),
        ],
        \array_map(
          function ($rgb) {
            return Color::create(...$rgb);
          },
          ColorThiefLibrary::getPalette($this->_image, $this->_numberOfColors - 2)
        )
      );
    }
  }
}

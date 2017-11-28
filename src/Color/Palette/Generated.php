<?php

namespace Carica\BitmapToSVG\Color\Palette {

  use Carica\BitmapToSVG\Color;

  class Generated extends Color\Palette {

    private $_numberOfColors;

    public function __construct(int $numberOfColors) {
      $this->_numberOfColors = $numberOfColors;
    }

    public function generate(): array {
      $numberOfColors = $this->_numberOfColors;
      $result = [];
      if ($numberOfColors < 8) {
        // if less then 8 colors generate a grayscale palette
        $steps = \floor(255 / ($numberOfColors-1));
        for($i = 0; $i < $numberOfColors; $i++) {
          $value = $i * $steps;
          $result[] = Color::createGray($value);
        }
      } else {
        $numberOfEdgeColors = \floor($numberOfColors ** (1/3));
        $steps = \floor(255 / ($numberOfEdgeColors - 1));
        $numberOfRandomColors = $numberOfColors - ($numberOfEdgeColors ** 3);
        for ($r = 0; $r < $numberOfEdgeColors; $r++) {
          for ($g = 0; $g < $numberOfEdgeColors; $g++) {
            for ($b = 0; $b < $numberOfEdgeColors; $b++) {
              $result[] = Color::create($r * $steps, $g * $steps, $b * $steps);
            }
          }
        }
        for ($i = 0; $i < $numberOfRandomColors; $i++) {
          $result[] = Color::createRandom(255);
        }
      }
      return $result;
    }
  }
}

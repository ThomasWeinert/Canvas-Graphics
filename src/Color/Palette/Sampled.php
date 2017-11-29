<?php

namespace Carica\BitmapToSVG\Color\Palette {

  use Carica\BitmapToSVG\Color;

  class Sampled extends Color\Palette {

    private $_numberOfColors;
    private $_image;

    public function __construct($image, int $numberOfColors) {
      $this->_image = $image;
      $this->_numberOfColors = $numberOfColors;
    }

    public function generate(): array {
      $image = $this->_image;
      $numberOfColors = $this->_numberOfColors;
      $result = [];

      $stepsX = \ceil(\sqrt($numberOfColors));
      $stepsY = \ceil($numberOfColors / $stepsX);
      $factorX = \imagesx($image) / ($stepsX + 1);
      $factorY = \imagesy($image) / ($stepsY + 1);
      for ($y = 0; $y < $stepsY; $y++) {
        for ($x = 0; $x < $stepsX; $x++) {
          if (\count($result) >= $numberOfColors) {
            return array_values($result);
          }
          $rgba = \imagecolorat($image, $x * $factorX, $y * $factorY);
          $color = Color::createFromArray(
            Color::removeAlphaFromColor(
              [
                'red' => ($rgba >> 16) & 0xFF,
                'green' => ($rgba >> 8) & 0xFF,
                'blue' => $rgba & 0xFF,
                'alpha' =>  (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
              ]
            )
          );
          $result[$color->asHexString()] = $color;
        }
      }
      return array_values($result);
    }
  }
}

<?php

namespace Carica\CanvasGraphics\Color\Palette {

  use Carica\CanvasGraphics\Canvas\ImageData;
  use Carica\CanvasGraphics\Color;

  class Sampled extends Color\Palette {

    private $_numberOfColors;
    private $_imageData;
    private $_backgroundColor;

    public function __construct(ImageData $imageData, int $numberOfColors, Color $backgroundColor = NULL) {
      $this->_imageData = $imageData;
      $this->_numberOfColors = $numberOfColors;
      $this->_backgroundColor = $backgroundColor ?? Color::createGray(255);
    }

    public function generate(): array {
      $imageData = $this->_imageData;
      $numberOfColors = $this->_numberOfColors;
      $result = [];

      $stepsX = \ceil(\sqrt($numberOfColors));
      $stepsY = \ceil($numberOfColors / $stepsX);
      $factorX = $imageData->width / ($stepsX + 1);
      $factorY = $imageData->height / ($stepsY + 1);
      for ($y = 0; $y < $stepsY; $y++) {
        for ($x = 0; $x < $stepsX; $x++) {
          if (\count($result) >= $numberOfColors) {
            return array_values($result);
          }
          $index = \round($y * $factorY * $imageData->width + $x * $factorY) * 4;
          $color = Color::createFromArray(
            Color::removeAlphaFromColor(
              [
                'red' => $imageData->data[$index],
                'green' => $imageData->data[$index+1],
                'blue' => $imageData->data[$index+2],
                'alpha' => $imageData->data[$index+3]
              ],
              $this->_backgroundColor
            )
          );
          $result[$color->toHexString()] = $color;
        }
      }
      return array_values($result);
    }
  }
}

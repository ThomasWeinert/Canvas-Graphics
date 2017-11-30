<?php

namespace Carica\BitmapToSVG {


  class Comparator {

    private $_cache = [];

    /**
     * @param resource $imageA
     * @param resource $imageB
     * @param float $accuracy percentage of pixels sampled between 0.2 (20%) and 1 (all pixels)
     * @return float Score between 0 (not equal) and 1 (equal)
     * @throws \LogicException
     */
    public function getScore($imageA, $imageB, float $accuracy = 1): float {
      $imageWidth = imagesx($imageA);
      $imageHeight = imagesy($imageA);
      if ($imageWidth !== imagesx($imageB) || $imageHeight !== imagesy($imageB)) {
        throw new \LogicException('Booth images need to have the same size.');
      }

      $accuracy = Utility::clampNumber($accuracy, 0.2, 1);
      if ($accuracy > 0.99) {
        $allPixels = TRUE;
        $xSamples = $imageWidth;
        $ySamples = $imageHeight;
        $xPixelsPerSample = 1;
        $yPixelsPerSample = 1;
      } else {
        $xSamples = floor($imageWidth * $accuracy);
        $ySamples = floor($imageHeight * $accuracy);
        $xPixelsPerSample = $imageWidth / $xSamples;
        $yPixelsPerSample = $imageHeight / $ySamples;
        $allPixels = FALSE;
      }

      $difference = 0;
      $this->_cache = [];
      for ($y = 0; $y < $imageHeight; $y += $yPixelsPerSample) {
        for ($x = 0; $x < $imageWidth; $x += $xPixelsPerSample) {
          if ($allPixels) {
            $pixelX = $x;
            $pixelY = $y;
          } else {
            $pixelX = floor($x);
            $pixelY = floor($y);
          }
          $difference += $this->getPixelDistance(
            $this->getPixel($imageA, $pixelX, $pixelY),
            $this->getPixel($imageB, $pixelX, $pixelY)
          );
        }
      }
      return 1 - ($difference / ($xSamples * $ySamples));
    }

    private function getPixel($image, $x, $y) {
      $rgba = \imagecolorat($image, $x, $y);
      if (isset($this->_cache[$rgba])) {
        return $this->_cache[$rgba];
      }
      return $this->_cache[$rgba] = [
        'red' => ($rgba >> 16) & 0xFF,
        'green' => ($rgba >> 8) & 0xFF,
        'blue' => $rgba & 0xFF,
        'alpha' =>  (127 - (($rgba & 0x7F000000) >> 24)) / 127 * 255
      ];
    }

    public function getPixelDistance($a, $b) {
      return (
        \abs($a['red'] - $b['red']) +
        \abs($a['green'] - $b['green']) +
        \abs($a['blue'] - $b['blue'])
      ) / (255 * 3);
    }
  }
}

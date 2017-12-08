<?php

namespace Carica\CanvasGraphics {


  use Carica\CanvasGraphics\Canvas\ImageData;

  class Comparator {

    private $_cache = [];

    /**
     * @param ImageData $imageA
     * @param ImageData $imageB
     * @param float $accuracy percentage of pixels sampled between 0.2 (20%) and 1 (all pixels)
     * @return float Score between 0 (not equal) and 1 (equal)
     * @throws \LogicException
     */
    public function getScore(ImageData $imageA, ImageData $imageB, float $accuracy = 1): float {
      $imageWidth = $imageA->width;
      $imageHeight = $imageA->height;
      if ($imageWidth !== $imageB->width || $imageHeight !== $imageB->height) {
        throw new \LogicException('Booth images need to have the same size.');
      }

      $accuracy = \max(0.2, \min($accuracy, 1));
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
            $index = ($y * $imageWidth + $x) * 4;
          } else {
            $index = (\floor($y) * $imageWidth + floor($x)) * 4;
          }
          $difference += $this->getPixelDistance(
            $this->getPixel($imageA->data, $index),
            $this->getPixel($imageB->data, $index)
          );
        }
      }
      return 1 - ($difference / ($xSamples * $ySamples));
    }

    private function getPixel(array $data, $index): array {
      return [
        $data[$index], $data[$index + 1], $data[$index + 2], $data[$index + 3]
      ];
    }

    public function getPixelDistance($a, $b) {
      return (
        \abs($a[0] - $b[0]) +
        \abs($a[1] - $b[1]) +
        \abs($a[2] - $b[2])
      ) / (255 * 3);
    }
  }
}

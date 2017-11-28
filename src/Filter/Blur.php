<?php

namespace Carica\BitmapToSVG\Filter {

  /**
   * Strong Gaussian Blur
   *
   * based on http://stackoverflow.com/a/20264482
   */
  class Blur {

    public function __construct(int $blurFactor = 3) {
      $this->_factor = $blurFactor;
    }

    public function apply(&$image) {
      if ($this->_factor < 1) {
        return;
      }

      $originalWidth = \imagesx($image);
      $originalHeight = \imagesy($image);

      $smallestWidth = \ceil($originalWidth * (0.5 ** $this->_factor));
      $smallestHeight = \ceil($originalHeight * (0.5 ** $this->_factor));

      // for the first run, the previous image is the original input
      $prevImage = $nextImage = $image;
      $prevWidth = $nextWidth = $originalWidth;
      $prevHeight = $nextHeight = $originalHeight;

      // scale way down and gradually scale back up, blurring all the way
      for($i = 1; $i < $this->_factor; $i++) {
        // determine dimensions of next image
        $nextWidth = $smallestWidth * (2 ** $i);
        $nextHeight = $smallestHeight * (2 ** $i);

        // resize previous image to next size
        $nextImage = \imagecreatetruecolor($nextWidth, $nextHeight);
        \imagecopyresized(
          $nextImage, $prevImage,
          0, 0, 0, 0,
          $nextWidth, $nextHeight, $prevWidth, $prevHeight
        );

        // apply blur filter
        \imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);

        // cleanup $prevImage
        if ($prevImage !== $image) {
          \imagedestroy($prevImage);
        }

        // now the new image becomes the previous image for the next step
        $prevImage = $nextImage;
        $prevWidth = $nextWidth;
        $prevHeight = $nextHeight;
      }

      $result = \imagecreatetruecolor($originalWidth, $originalHeight);
      // scale back to original size and blur one more time
      \imagecopyresized(
        $result, $nextImage,
        0, 0, 0, 0,
        $originalWidth, $originalHeight, $nextWidth, $nextHeight
      );
      \imagefilter($result, IMG_FILTER_GAUSSIAN_BLUR);

      // clean up
      if ($nextImage !== $image) {
        \imagedestroy($nextImage);
      }
      \imagedestroy($image);

      $image = $result;
    }
  }
}


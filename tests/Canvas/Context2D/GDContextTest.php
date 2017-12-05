<?php

namespace Carica\CanvasGraphics\Canvas\Context2D {

  use PHPUnit\Framework\TestCase;

  class GDContextTest extends TestCase {

    private $_image;

    public function tearDown() {
      if (NULL !== $this->_image) {
        \imagedestroy($this->_image);
      }
    }

    public function testCreateImageData() {
      $this->_image = $image = \imagecreatetruecolor(1,1);
      $context = new GDCanvasContext($image);
      $imageData = $context->createImageData(3, 3);
      $this->assertSame(
        [
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0,
          0, 0, 0, 0
        ],
        $imageData->data
      );
      $this->assertSame(3, $imageData->width);
      $this->assertSame(3, $imageData->height);
    }

    public function testGetImageData() {
      $this->_image = $image = \imagecreatetruecolor(2,2);
      \imagesetpixel($image, 0, 0, \imagecolorallocate($image, 255, 255, 255));
      \imagesetpixel($image, 1, 0, \imagecolorallocate($image, 255, 0, 0));
      \imagesetpixel($image, 0, 1, \imagecolorallocate($image, 0, 255, 0));
      \imagesetpixel($image, 1, 1, \imagecolorallocate($image, 0, 0, 255));

      $context = new GDCanvasContext($image);
      $imageData = $context->getImageData();
      $this->assertSame(
        [
          255, 255, 255, 255, // 0, 0 - white
          255, 0, 0, 255, // 1, 0 - red
          0, 255, 0, 255, // 0, 1 - green
          0, 0, 255, 255, // 1, 1 - blue
        ],
        $imageData->data
      );
      $this->assertSame(2, $imageData->width);
      $this->assertSame(2, $imageData->height);
    }

  }
}

<?php

namespace Carica\CanvasGraphics {

  use Carica\CanvasGraphics\Canvas\GD\CanvasContext;
  use PHPUnit\Framework\TestCase;

  class ComparatorTest extends TestCase {

    /**
     * @covers \Carica\CanvasGraphics\Comparator
     */
    public function testCompareSameImageExpecting100Percent() {
      $image = imagecreatetruecolor(10, 10);
      imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 0,0,0));
      $imageData = (new CanvasContext($image))->getImageData();

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageData, $imageData);
      $this->assertEquals(1, $difference);
    }

    /**
     * @covers \Carica\CanvasGraphics\Comparator
     */
    public function testCompareSameImageWithHalfAccuracyExpecting100Percent() {
      $image = imagecreatetruecolor(10, 10);
      imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 0,0,0));
      $imageData = (new CanvasContext($image))->getImageData();

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageData, $imageData, 0.5);
      $this->assertEquals(1, $difference);
    }

    /**
     * @covers \Carica\CanvasGraphics\Comparator
     */
    public function testCompareBlackAndWhiteImagesExpecting0Percent() {
      $imageA = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageA, 0, 0, 9, 9, imagecolorallocate($imageA, 0,0,0));
      $imageB = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageB, 0, 0, 9, 9, imagecolorallocate($imageB, 255,255,255));
      $imageDataA = (new CanvasContext($imageA))->getImageData();
      $imageDataB = (new CanvasContext($imageB))->getImageData();

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageDataA, $imageDataB);
      $this->assertEquals(0, $difference);
    }

    /**
     * @covers \Carica\CanvasGraphics\Comparator
     */
    public function testCompareBlackAndHalfWhiteImagesExpecting50Percent() {
      $imageA = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageA, 0, 0, 9, 9, imagecolorallocate($imageA, 0,0,0));
      $imageB = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageB, 0, 0, 4, 9, imagecolorallocate($imageB, 255,255,255));
      $imageDataA = (new CanvasContext($imageA))->getImageData();
      $imageDataB = (new CanvasContext($imageB))->getImageData();

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageDataA, $imageDataB);
      $this->assertEquals(0.5, $difference);
    }

    /**
     * @covers \Carica\CanvasGraphics\Comparator
     */
    public function testCompareImagesWithDifferenceSizeExpectingException() {
      $imageA = imagecreatetruecolor(10, 10);
      $imageB = imagecreatetruecolor(5, 5);
      $imageDataA = (new CanvasContext($imageA))->getImageData();
      $imageDataB = (new CanvasContext($imageB))->getImageData();

      $comparator = new Comparator();
      $this->expectException(\LogicException::class);
      $comparator->getScore($imageDataA, $imageDataB);
    }
  }
}

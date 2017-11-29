<?php

namespace Carica\BitmapToSVG {

  use PHPUnit\Framework\TestCase;

  class ComparatorTest extends TestCase {

    /**
     * @covers \Carica\BitmapToSVG\Comparator
     */
    public function testCompareSameImageExpecting100Percent() {
      $image = imagecreatetruecolor(10, 10);
      imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 0,0,0));
      $comparator = new Comparator();
      $difference = $comparator->getScore($image, $image);
      $this->assertEquals(1, $difference);
    }

    /**
     * @covers \Carica\BitmapToSVG\Comparator
     */
    public function testCompareSameImageWithHalfAccuracyExpecting100Percent() {
      $image = imagecreatetruecolor(10, 10);
      imagefilledrectangle($image, 0, 0, 9, 9, imagecolorallocate($image, 0,0,0));
      $comparator = new Comparator();
      $difference = $comparator->getScore($image, $image, 0.5);
      $this->assertEquals(1, $difference);
    }

    /**
     * @covers \Carica\BitmapToSVG\Comparator
     */
    public function testCompareBlackAndWhiteImagesExpecting0Percent() {
      $imageA = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageA, 0, 0, 9, 9, imagecolorallocate($imageA, 0,0,0));
      $imageB = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageB, 0, 0, 9, 9, imagecolorallocate($imageB, 255,255,255));

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageA, $imageB);
      $this->assertEquals(0, $difference);
    }

    /**
     * @covers \Carica\BitmapToSVG\Comparator
     */
    public function testCompareBlackAndHalfWhiteImagesExpecting50Percent() {
      $imageA = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageA, 0, 0, 9, 9, imagecolorallocate($imageA, 0,0,0));
      $imageB = imagecreatetruecolor(10, 10);
      imagefilledrectangle($imageB, 0, 0, 4, 9, imagecolorallocate($imageB, 255,255,255));

      $comparator = new Comparator();
      $difference = $comparator->getScore($imageA, $imageB);
      $this->assertEquals(0.5, $difference);
    }

    /**
     * @covers \Carica\BitmapToSVG\Comparator
     */
    public function testCompareImagesWithDifferenceSizeExpectingException() {
      $imageA = imagecreatetruecolor(10, 10);
      $imageB = imagecreatetruecolor(5, 5);
      $comparator = new Comparator();
      $this->expectException(\LogicException::class);
      $difference = $comparator->getScore($imageA, $imageB);
    }
  }
}

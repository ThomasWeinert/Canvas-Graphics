<?php

namespace Carica\CanvasGraphics {

  use PHPUnit\Framework\TestCase;

  class ColorTest extends TestCase {

    public function testConstructor() {
      $color = new Color(1, 2, 3, 4);
      $this->assertEquals('#01020304', $color->toHexString(TRUE));
    }

    /**
     * @param $expectedDistance
     * @param array|Color $colorOne
     * @param array|Color $colorTwo
     * @dataProvider provideColorsAndDistances
     */
    public function testComputeDistance($expectedDistance, $colorOne, $colorTwo) {
      $distance = Color::computeDistance(Color::createFromArray($colorOne), Color::createFromArray($colorTwo));
      $this->assertEquals($expectedDistance, round($distance * 255));
    }

    public static function provideColorsAndDistances() {
      return [
        'black and black' => [0, [0,0,0], [0,0,0]],
        'black and white' => [255, [0,0,0], [255,255,255]],
        'black and red' => [120, [0,0,0], [255,0,0]]
      ];
    }


    /**
     * @param string $expected
     * @param array|Color $color
     * @param null|Color $background
     * @dataProvider provideColorsForMerging
     */
    public function testRemoveAlphaFromColor($expected, $color, $background = NULL) {
      $color = Color::removeAlphaFromColor(Color::createFromArray($color), $background);
      $this->assertEquals($expected, $color->toHexString());
    }

    public static function provideColorsForMerging() {
      return [
        'red without alpha on default (white)' => ['#f00', [255, 0, 0, 255]],
        'red with alpha 127 on default white' => ['#ff8080', [255, 0, 0, 127]]
      ];
    }
  }
}

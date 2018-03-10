<?php

namespace Carica\CanvasGraphics {

  use PHPUnit\Framework\TestCase;

  /**
   * @coversDefaultClass \Carica\CanvasGraphics\Color
   */
  class ColorTest extends TestCase {

    /**
     * @covers ::__construct
     * @covers ::__toString
     */
    public function testConstructor(): void {
      $color = new Color(1, 2, 3, 4);
      $this->assertEquals('rgba(1, 2, 3, 4), hsl(0.62, 1.00, 0.01)', (string)$color);
    }

    /**
     *
     * @covers ::computeDistance
     * @param $expectedDistance
     * @param array|Color $colorOne
     * @param array|Color $colorTwo
     *
     * @testWith
     *   [0, [0,0,0], [0,0,0]]
     *   [255, [0,0,0], [255,255,255]]
     *   [120, [0,0,0], [255,0,0]]
     */
    public function testComputeDistance($expectedDistance, $colorOne, $colorTwo): void {
      $distance = Color::computeDistance(Color::createFromArray($colorOne), Color::createFromArray($colorTwo));
      $this->assertEquals($expectedDistance, round($distance * 255));
    }

    /**
     * @covers ::removeAlphaFromColor
     *
     * @param string $expected
     * @param array|Color $color
     * @param null|Color $background
     *
     * @testWith
     *   ["#f00", [255, 0, 0, 255]]
     *   ["#ff8080", [255, 0, 0, 127]]
     */
    public function testRemoveAlphaFromColor($expected, $color, $background = NULL): void {
      $color = Color::removeAlphaFromColor(Color::createFromArray($color), $background);
      $this->assertEquals($expected, $color->toHexString());
    }
  }
}

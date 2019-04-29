<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  use Carica\CanvasGraphics\Color;
  use PHPUnit\Framework\TestCase;

  class PolygonTest extends TestCase {

    public function testConstructor() {
      $polygon = new Polygon(100, 100, new Color(255, 255, 255), 3);
      $this->assertCount(3, $polygon->getPoints());
    }

    public function testMutate() {
      $polygon = new Polygon(100, 100, new Color(255, 255, 255), 5);
      $mutation = $polygon->mutate();
      $this->assertCount(5, $mutation->getPoints());

      // mutation should change one point
      $difference = array_udiff_assoc(
        $polygon->getPoints(),
        $mutation->getPoints(),
        function($a, $b) {
          return (($a[0] === $b[0]) && ($a[1] === $b[1])) ? 0 : 1;
        }
      );
      $this->assertCount(1, $difference);
    }
  }

}

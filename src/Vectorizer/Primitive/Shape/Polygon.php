<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  class Polygon extends Shape {

    private $_points;
    private $_box;

    private $_minWidth = 5;
    private $_minHeight = 5;
    private $_minRatio = 0.1;

    public function appendTo(Document $svg): void {
      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;

      /** @var \DOMElement $path */
      $path = $parent->appendChild(
        $document->createElementNS(self::XMLNS_SVG, 'path')
      );
      $path->setAttribute('fill', $this->getColor()->toHexString());
      if ($this->getColor()->alpha < 255) {
        $path->setAttribute('fill-opacity', number_format($this->getColor()->alpha / 255, 1));
      }
      $dimensions = sprintf('M%d %d', ...$this->_points[0]);
      $lastSegmentType = 'M';
      for ($i = 1, $c = \count($this->_points); $i < $c; $i++) {
        $pointString = sprintf('L%d %d', ...$this->_points[$i]);
        $distanceString = sprintf(
          'l%d %d',
          $this->_points[$i][0] - $this->_points[$i-1][0],
          $this->_points[$i][1] - $this->_points[$i-1][1]
        );
        $segmentString = \strlen($pointString) > \strlen($distanceString) ? $distanceString : $pointString;
        $segmentType = $segmentString[0];
        if ($lastSegmentType === $segmentType && $segmentString[1] === '-') {
          $segmentString = \substr($segmentString, 1);
        } else {
          $lastSegmentType = $segmentType;
        }
        $dimensions .= $segmentString;
      }
      $dimensions .= 'Z';
      $path->setAttribute('d', str_replace(' -', '-', $dimensions));
    }

    public function __construct(int $width, int $height, int $corners) {
      parent::__construct($width, $height);
      $this->_points = $this->getMutatedPoints($this->_createPoints($width, $height, $corners));
    }

    public function render(CanvasContext2D $context): void {
      $context->beginPath();
      $context->moveTo(...$this->_points[0]);
      for ($i = 1, $c = \count($this->_points); $i < $c; $i++) {
        $context->lineTo(...$this->_points[$i]);
      }
      $context->closePath();
      $context->fill();
    }

    public function mutate():Shape {
      $mutation = clone $this;
      $mutation->_points = $this->getMutatedPoints($this->_points);
      $mutation->_box = NULL;
      return $mutation;
    }

    private function getMutatedPoints(array $points): array {
      $limit = 0;
      do {
        if (++$limit > 100) {
          return $points;
        }
        $mutatedPoints = $points;
        $index = \random_int(0, \count($mutatedPoints) - 1);
        $mutatedPoints[$index] = $this->_createNextPoint(...$mutatedPoints[$index]);
        $boundingBox = $this->getBoxForPoints($mutatedPoints);
      } while (
        $boundingBox['width'] < $this->_minWidth ||
        $boundingBox['height'] < $this->_minHeight ||
        ($boundingBox['width'] / $boundingBox['height']) < $this->_minRatio ||
        ($boundingBox['height'] / $boundingBox['width']) < $this->_minRatio ||
        $this->isOutsideImage($boundingBox)
      );
      return $mutatedPoints;
    }

    public function getBoundingBox():array {
      if (NULL === $this->_box) {
        $this->_box = $this->getBoxForPoints($this->_points);
      }
      return $this->_box;
    }

    private function getBoxForPoints(array $points): array {
      $boundingBox = \array_reduce(
        $points,
        function ($carry, $point) {
          [$x, $y] = $point;
          if (!isset($carry['top']) || $carry['top'] > $y) {
            $carry['top'] = $y;
          }
          if (!isset($carry['right']) || $carry['right'] < $x) {
            $carry['right'] = $x;
          }
          if (!isset($carry['bottom']) || $carry['bottom'] < $y) {
            $carry['bottom'] = $y;
          }
          if (!isset($carry['left']) || $carry['left'] > $x) {
            $carry['left'] = $x;
          }
          return $carry;
        },
        ['top' => NULL, 'right' => NULL, 'bottom' => NULL, 'left' => NULL]
      );
      $boundingBox['width'] = $boundingBox['right'] - $boundingBox['left'];
      $boundingBox['height'] = $boundingBox['bottom'] - $boundingBox['top'];
      return $boundingBox;
    }

    private function _createPoints(int $width, int $height, int $count) {
      $first = self::createRandomPoint($width, $height);
		  $points = [$first];

		  for ($i = 1; $i < $count; $i++) {
		    $points[] = $this->_createNextPoint(...$first);
		  }
      return $points;
    }

    private function _createNextPoint($fromX, $fromY) {
      $angle = \random_int(0, 359) / 360 * 2 * M_PI;
      $radius = \random_int(1, $this->_maxDistance);
      return [
        (int)($fromX + \floor($radius * \cos($angle))),
        (int)($fromY + \floor($radius * \sin($angle)))
      ];
    }

    public function getPoints() {
      return $this->_points;
    }
  }
}



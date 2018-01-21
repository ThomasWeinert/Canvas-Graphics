<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  class RotatedRectangle extends Shape {

    private $_center;
    private $_width;
    private $_height;
    private $_angle;

    private $_points = [];
    private $_box;

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

    public function __construct(int $width, int $height) {
      parent::__construct($width, $height);
      $this->_center = self::createRandomPoint($width, $height);
      $this->_width = \random_int(0, $width);
      $this->_height = \random_int(0, $height);
      $this->_angle = \random_int(0, 359) * M_PI / 180;
      $this->getBoundingBox();
    }

    private function getRotatedPoint(int $x, int $y, float $angle, int $centerX, int $centerY) {
      $offsetX = $x - $centerX;
      $offsetY = $y - $centerY;
      return [
        (int)($centerX + ($offsetX  * cos($angle)) - ($offsetY * sin($angle))),
        (int)($centerY + ($offsetX  * sin($angle)) + ($offsetY * cos($angle)))
      ];
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
      $amount = \random_int(1, $this->_maxDistance) - \floor($this->_maxDistance / 2);

      switch (\random_int(0, 4)) {
      case 0: /* center x */
        $mutation->_center[0] += $amount;
        break;
      case 1: /* center y */
        $mutation->_center[1] += $amount;
        break;
      case 2: /* width */
        $mutation->_width = \abs($this->_width + $amount);
        break;
      case 3: /* height */
        $mutation->_height = \abs($this->_height + $amount);
        break;
      case 4: /* angle */
        $mutation->_angle += ($amount * M_PI / 180);
        break;
      }
      $mutation->_box = NULL;
      return $mutation;
    }

    public function getBoundingBox():array {
      $halfWidth = $this->_width / 2;
      $halfHeight = $this->_height / 2;
      $points = [
        [ // left top
          \round($this->_center[0] - $halfWidth),
          \round($this->_center[1] - $halfHeight),
        ],
        [ // right top
          \round($this->_center[0] + $halfWidth),
          \round($this->_center[1] - $halfHeight),
        ],
        [ // right bottom
          \round($this->_center[0] + $halfWidth),
          \round($this->_center[1] + $halfHeight),
        ],
        [ // left bottom
          \round($this->_center[0] - $halfWidth),
          \round($this->_center[1] + $halfHeight),
        ]
      ];
      $this->_points = [];
      $xValues = [];
      $yValues = [];
      foreach ($points as $point) {
        $this->_points[] = $rotatedPoint = $this->getRotatedPoint(
          $point[0], $point[1], $this->_angle, ...$this->_center
        );
        $xValues[] = $rotatedPoint[0];
        $yValues[] = $rotatedPoint[1];
//        echo '<pre>', var_dump($this->_angle), '</pre>';
      }
      if (NULL === $this->_box) {
        $boundingBox = [
          'top' => \min($yValues),
          'right'  => \max($xValues),
          'bottom' => \max($yValues),
          'left' => \min($xValues)
        ];
        $boundingBox['width'] = $boundingBox['right'] - $boundingBox['left'];
        $boundingBox['height'] = $boundingBox['bottom'] - $boundingBox['top'];
        $this->_box = $boundingBox;
      }
      return $this->_box;
    }
  }
}



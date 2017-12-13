<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  class Rectangle extends Shape {

    private $_left;
    private $_top;
    private $_right;
    private $_bottom;

    private $_box;
    private $_maxDistance = 20;

    public function appendTo(Document $svg): void {
      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;

      /** @var \DOMElement $Node */
      $Node = $parent->appendChild(
        $document->createElementNS(self::XMLNS_SVG, 'rect')
      );
      $shapeNodeNode->setAttribute('x', $this->_left);
      $Node->setAttribute('y', $this->_top);
      $Node->setAttribute('width', $this->_right - $this->_left);
      $Node->setAttribute('height', $this->_bottom - $this->_top);
      $Node->setAttribute('fill', $this->getColor()->toHexString());
      if ($this->getColor()->alpha < 255) {
        $shapeNode->setAttribute('fill-opacity', number_format($this->getColor()->alpha / 255, 1));
      }
    }

    public function __construct(int $width, int $height) {
      $x1 = self::createRandomPoint($width, $height);
      $x2 = self::createRandomPoint($width, $height);
      $this->_left = \min($x1[0], $x2[0]);
      $this->_top = \min($x1[1], $x2[1]);
      $this->_right = \max($x1[0], $x2[0]);
      $this->_bottom = \max($x1[1], $x2[1]);
      $this->getBoundingBox();
    }

    public function render(CanvasContext2D $context): void {
      $context->fillRect(
        $this->_left, $this->_top, $this->_right - $this->_left, $this->_bottom - $this->_top
      );
    }

    public function mutate():Shape {
      $mutation = clone $this;
      $amount = \random_int(1, $this->_maxDistance) - \floor($this->_maxDistance / 2);

      do {
        switch (\random_int(0, 3)) {
        case 0: /* left */
          $mutation->_left += $amount;
          break;
        case 1: /* top */
          $mutation->_top += $amount;
          break;
        case 2: /* right */
          $mutation->_right += $amount;
          break;
        case 3: /* bottom */
          $mutation->_bottom += $amount;
          break;
        }
        if ($mutation->_left > $mutation->_right) {
          $left = $mutation->_right;
          $mutation->_right = $mutation->_left;
          $mutation->_left = $left;
        }
        if ($mutation->_top > $mutation->_bottom) {
          $top = $mutation->_bottom;
          $mutation->_bottom = $mutation->_top;
          $mutation->_top = $top;
        }
      } while ($mutation->_left === $mutation->_right || $mutation->_top === $mutation->_bottom);

      $mutation->_box = NULL;
      return $mutation;
    }

    public function getBoundingBox():array {
      if (NULL === $this->_box) {
        $boundingBox = [
          'top' => $this->_top,
          'right'  => $this->_right,
          'bottom' => $this->_bottom,
          'left' => $this->_left
        ];
        $boundingBox['width'] = $boundingBox['right'] - $boundingBox['left'];
        $boundingBox['height'] = $boundingBox['bottom'] - $boundingBox['top'];
        $this->_box = $boundingBox;
      }
      return $this->_box;
    }
  }
}



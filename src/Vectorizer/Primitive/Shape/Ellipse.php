<?php
namespace Carica\CanvasGraphics\Vectorizer\Primitive\Shape {

  use Carica\CanvasGraphics\Canvas\CanvasContext2D;
  use Carica\CanvasGraphics\Color;
  use Carica\CanvasGraphics\SVG\Document;
  use Carica\CanvasGraphics\Vectorizer\Primitive\Shape;

  class Ellipse extends Shape {

    private $_centerX;
    private $_centerY;
    private $_radiusX;
    private $_radiusY;

    private $_box;

    public function appendTo(Document $svg): void {
      $parent = $svg->getShapesNode();
      $document = $parent->ownerDocument;

      /** @var \DOMElement $shapeNode */
      $shapeNode = $parent->appendChild(
        $document->createElementNS(self::XMLNS_SVG, 'ellipse')
      );
      $shapeNode->setAttribute('cx', $this->_centerX);
      $shapeNode->setAttribute('cy', $this->_centerY);
      $shapeNode->setAttribute('rx', $this->_radiusX);
      $shapeNode->setAttribute('ry', $this->_radiusY);
      $shapeNode->setAttribute('fill', $this->getColor()->toHexString());
      if ($this->getColor()->alpha < 255) {
        $shapeNode->setAttribute('fill-opacity', number_format($this->getColor()->alpha / 255, 1));
      }
    }

    public function __construct(int $width, int $height, Color $backgroundColor) {
      parent::__construct($width, $height, $backgroundColor);
      [$this->_centerX, $this->_centerY] = self::createRandomPoint($width, $height);
      $this->_radiusX = 1 + \random_int(1, $this->_maxDistance);
      $this->_radiusY = 1 + \random_int(1, $this->_maxDistance);
      $this->getBoundingBox();
    }

    public function render(CanvasContext2D $context): void {
      $context->beginPath();
      $context->ellipse($this->_centerX, $this->_centerY, $this->_radiusX, $this->_radiusY);
      $context->fill();
    }

    public function mutate():Shape {
      $mutation = clone $this;

      switch (\random_int(0, 2)) {
      case 0: /* center */
        $angle = \mt_rand() * M_2_PI;
        $radius = \mt_rand() * $this->_maxDistance;
        $mutation->_centerX += \floor($radius * \cos($angle));
        $mutation->_centerY += \floor($radius * \sin($angle));
        break;
      case 1: /* radiusX */
        $mutation->_radiusX += \random_int(1, $this->_maxDistance) - \floor($this->_maxDistance / 2);
        $mutation->_radiusX = \max(1, \floor($mutation->_radiusX));
        break;
      case 2: /* radiusY */
        $mutation->_radiusY += \random_int(1, $this->_maxDistance) - \floor($this->_maxDistance / 2);
        $mutation->_radiusY = \max(1, \floor($mutation->_radiusY));
        break;
      }

      $mutation->_box = NULL;
      return $mutation;
    }

    public function getBoundingBox():array {
      if (NULL === $this->_box) {
        $boundingBox = [
          'top' => $this->_centerY - $this->_radiusY,
          'right'  => $this->_centerX + $this->_radiusX,
          'bottom' => $this->_centerY + $this->_radiusY,
          'left' => $this->_centerX - $this->_radiusX
        ];
        $boundingBox['width'] = $boundingBox['right'] - $boundingBox['left'] + 1;
        $boundingBox['height'] = $boundingBox['bottom'] - $boundingBox['top'] + 1;
        $this->_box = $boundingBox;
      }
      return $this->_box;
    }
  }
}



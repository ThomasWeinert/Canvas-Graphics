<?php

namespace Carica\CanvasGraphics\SVG\Loading {

  use Carica\CanvasGraphics\SVG\Appendable;
  use Carica\CanvasGraphics\SVG\Document;

  class TwoDots implements Appendable {

    private $_colorA;
    private $_colorB;
    private $_radius;

    public function __construct(
      int $radius = NULL, string $colorA = 'rgba(0,0,0,0.6)', string $colorB = 'rgba(255,255,255,0.6)'
    ) {
      $this->_radius = $radius;
      $this->_colorA = $colorA;
      $this->_colorB = $colorB;
    }

    public function appendTo(Document $svg): void {
      $width = $svg->getWidth();
      $height = $svg->getHeight();

      $x = \floor($width / 2);
      $y = \floor($height / 2);
      $radius = $this->_radius ?? round($width * 0.03);
      $offsetX = round($radius * 1.2);

      $document = $svg->getDocument();
      $this->appendDot($document, $this->_colorA, $x - $offsetX, $x + $offsetX, $y, $radius);
      $this->appendDot($document, $this->_colorB, $x + $offsetX, $x - $offsetX, $y, $radius);
    }

    private function appendDot(
      \DOMDocument $document, string $color, int $startX, int $endX, int $y, int $radius
    ) {
      $document->documentElement->appendChild(
        $circle = $document->createElementNS(self::XMLNS_SVG, 'circle')
      );
      $circle->setAttribute('cy', $y);
      $circle->setAttribute('r', $radius);
      $circle->setAttribute('fill', $color);
      $circle->appendChild(
        $animate = $document->createElementNS(self::XMLNS_SVG, 'animate')
      );
      $animate->setAttribute('attributeName', 'cx');
      $animate->setAttribute('values', sprintf('%1$d;%1$d;%2$d;%2$d;%1$d', $startX, $endX));
      $animate->setAttribute('keyTimes', '0;0.2;0.5;0.7;1');
      $animate->setAttribute('dur', '2s');
      $animate->setAttribute('repeatCount', 'indefinite');

    }
  }
}

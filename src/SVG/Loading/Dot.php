<?php

namespace Carica\CanvasGraphics\SVG\Loading {

  use Carica\CanvasGraphics\SVG\Appendable;
  use Carica\CanvasGraphics\SVG\Document;

  class Dot implements Appendable {

    public function appendTo(Document $svg): void {
      $width = $svg->getWidth();
      $height = $svg->getHeight();

      $x = \floor($width / 2);
      $y = \floor($height / 2);
      $radius = round($width * 0.1);
      $offsetX = round($radius * 1.2);

      $document = $svg->getDocument();
      $document->documentElement->appendChild(
        $circle = $document->createElementNS(self::XMLNS_SVG, 'circle')
      );
      $circle->setAttribute('cy', $y);
      $circle->setAttribute('r', $radius);
      $circle->setAttribute('fill', 'rgba(0,0,0,0.6)');

      $circle->appendChild(
        $animate = $document->createElementNS(self::XMLNS_SVG, 'animate')
      );
      $animate->setAttribute('attributeName', 'cx');
      $animate->setAttribute(
        'values',
        sprintf('%1$d;%1$d;%2$d;%2$d;%1$d', $x - $offsetX, $x + $offsetX)
      );
      $animate->setAttribute('keyTimes', '0;0.2;0.5;0.7;1');

      $animate->setAttribute('keyTimes', '0;0.5;1');
      $animate->setAttribute('dur', '2s');
      $animate->setAttribute('repeatCount', 'indefinite');
    }
  }
}

<?php

namespace Carica\CanvasGraphics\Canvas {

  use Carica\CanvasGraphics\Canvas\Path2D\Line;
  use Carica\CanvasGraphics\Canvas\Path2D\Move;

  class Path2D implements \IteratorAggregate {

    private $_segments = [];

    public function getIterator() {
      return new \ArrayIterator($this->_segments);
    }

    public function closePath() {
      if (\count($this->_segments) < 2) {
        return;
      }
      if (!($this->_segments[0] instanceof Move)) {
        throw new \LogicException('Can not close path. First segment is not a move providing a starting point.');
      }
      $this->_segments[] = new Line(...$this->_segments[0]->getTargetPoint());
    }

    public function moveTo(int $x, int $y) {
      $this->_segments[] = new Move($x, $y);
    }

    public function lineTo(int $x, int $y) {
      $this->_segments[] = new Line($x, $y);
    }
  }
}

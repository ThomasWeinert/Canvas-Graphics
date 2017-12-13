<?php

namespace Carica\CanvasGraphics\Canvas {

  use Carica\CanvasGraphics\Canvas\Path2D;

  class Path2D implements \IteratorAggregate, \Countable, \ArrayAccess {

    private $_segments = [];

    public function closePath() {
      if (\count($this->_segments) < 2) {
        return;
      }
      if (!($this->_segments[0] instanceof Path2D\Move)) {
        throw new \LogicException('Can not close path. First segment is not a move providing a starting point.');
      }
      $this->_segments[] = new Path2D\Line(...$this->_segments[0]->getTargetPoint());
    }

    public function moveTo(int $x, int $y) {
      $this->_segments[] = new Path2D\Move($x, $y);
    }

    public function lineTo(int $x, int $y) {
      $this->_segments[] = new Path2D\Line($x, $y);
    }

    public function ellipse(int $centerX, int $centerY, int $radiusX, int $radiusY) {
      $this->_segments[] = new Path2D\Ellipse($centerX, $centerY, $radiusX,  $radiusY);
    }

    public function rect(int $x, int $y, int $width, int $height) {
      $this->_segments[] = new Path2D\Rectangle($x, $y, $width, $height);
    }

    /** Interfaces */

    public function getIterator() {
      return new \ArrayIterator($this->_segments);
    }

    public function count() {
      return \count($this->_segments);
    }

    public function offsetExists($offset) {
      return isset($this->_segments[$offset]);
    }

    public function offsetGet($offset) {
      return $this->_segments[$offset];
    }

    public function offsetSet($offset, $value) {
      throw new \BadMethodCallException('Please use the specific methods to modify the path.');
    }
    public function offsetUnset($offset) {
      throw new \BadMethodCallException('Please use the specific methods to modify the path.');
    }
  }
}

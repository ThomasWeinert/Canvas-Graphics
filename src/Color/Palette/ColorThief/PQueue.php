<?php

namespace Carica\CanvasGraphics\Color\Palette\ColorThief {

  /* Simple priority queue */

  class PQueue implements \IteratorAggregate, \Countable {
    private $_contents = [];
    private $_sorted = FALSE;
    private $_comparator = NULL;

    public function __construct($comparator) {
      $this->setComparator($comparator);
    }

    private function sort() {
      usort($this->_contents, $this->_comparator);
      $this->_sorted = TRUE;
    }

    public function push($object) {
      $this->_contents[] = $object;
      $this->_sorted = FALSE;
    }

    public function peek(int $index = NULL) {
      if (!$this->_sorted) {
        $this->sort();
      }

      if ($index === NULL) {
        $index = $this->count() - 1;
      }

      return $this->_contents[$index];
    }

    public function pop() {
      if (!$this->_sorted) {
        $this->sort();
      }
      return \array_pop($this->_contents);
    }

    public function count(): int {
      return \count($this->_contents);
    }

    public function map($function) {
      return \array_map($function, $this->_contents);
    }

    public function setComparator($function): void {
      $this->_comparator = $function;
      $this->_sorted = FALSE;
    }

    public function getIterator(): \Iterator {
      if (!$this->_sorted) {
        $this->sort();
      }
      return new \ArrayIterator($this->_contents);
    }
  }
}

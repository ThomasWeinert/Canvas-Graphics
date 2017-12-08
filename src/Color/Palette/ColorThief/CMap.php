<?php

namespace Carica\CanvasGraphics\Color\Palette\ColorThief {

  /* Color map */
  use Carica\CanvasGraphics\Color\Palette\ColorThief;

  class CMap implements \Countable {
    private $_queue;

    public function __construct() {
      $this->_queue = new PQueue(
        function ($a, $b) {
          return ColorThief::compareNumber(
            $a['vbox']->count() * $a['vbox']->volume(),
            $b['vbox']->count() * $b['vbox']->volume()
          );
        }
      );
    }

    public function push(VBox $vbox) {
      $this->_queue->push(
        [
          'vbox' => $vbox,
          'color' => $vbox->avg()
        ]
      );
    }

    public function palette() {
      return $this->_queue->map(
        function (array $vb) {
          return $vb['color'];
        }
      );
    }

    public function count() {
      return \count($this->_queue);
    }

    public function map($color) {
      $vboxes_size = \count($this->_queue);
      for ($i = 0; $i < $vboxes_size; $i++) {
        $vbox = $this->_queue->peek($i);
        if ($vbox['vbox']->contains($color)) {
          return $vbox['color'];
        }
      }

      return $this->nearest($color);
    }

    public function nearest($color) {
      $pColor = NULL;
      $vboxes_size = \count($this->_queue);
      $d1 = NULL;
      for ($i = 0; $i < $vboxes_size; $i++) {
        $vbox = $this->_queue->peek($i);
        $d2 = \sqrt(
          (($color[0] - $vbox['color'][0]) ** 2) +
          (($color[1] - $vbox['color'][1]) ** 2) +
          (($color[2] - $vbox['color'][2]) ** 2)
        );

        if (!isset($d1) || $d2 < $d1) {
          $d1 = $d2;
          $pColor = $vbox['color'];
        }
      }

      return $pColor;
    }

    public function forcebw() {
      // XXX: won't work yet
      /*
      vboxes = this.vboxes;
      vboxes.sort(function (a,b) { return pv.naturalOrder(pv.sum(a.color), pv.sum(b.color) )});

      // force darkest color to black if everything < 5
      var lowest = vboxes[0].color;
      if (lowest[0] < 5 && lowest[1] < 5 && lowest[2] < 5)
          vboxes[0].color = [0,0,0];

      // force lightest color to white if everything > 251
      var idx = vboxes.length-1,
          highest = vboxes[idx].color;
      if (highest[0] > 251 && highest[1] > 251 && highest[2] > 251)
          vboxes[idx].color = [255,255,255];
      */
    }
  }
}

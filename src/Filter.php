<?php

namespace Carica\CanvasGraphics {

  interface Filter {

    public function apply(&$image): void;
  }
}


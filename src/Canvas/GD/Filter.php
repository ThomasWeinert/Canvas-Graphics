<?php

namespace Carica\CanvasGraphics\Canvas\GD {

  interface Filter {

    public function applyTo(&$image): void;
  }
}


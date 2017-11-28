<?php

namespace Carica\BitmapToSVG {

  interface Filter {

    public function apply(&$image);
  }
}


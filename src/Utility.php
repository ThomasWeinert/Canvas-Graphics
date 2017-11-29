<?php

namespace Carica\BitmapToSVG {


  abstract class Utility {

    public static function clampNumber($value, $minimum, $maximum) {
      return \max($minimum, \min($value, $maximum));
    }
  }
}

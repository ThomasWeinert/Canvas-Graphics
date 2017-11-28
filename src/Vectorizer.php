<?php

namespace Carica\BitmapToSVG {

  interface Vectorizer {

    public const XMLNS_SVG = 'http://www.w3.org/2000/svg';

    public function toSVG($image): \DOMDocument;
  }
}

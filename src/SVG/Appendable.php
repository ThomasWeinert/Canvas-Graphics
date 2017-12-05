<?php

namespace Carica\CanvasGraphics\SVG {

  interface Appendable {

    public const XMLNS_SVG = 'http://www.w3.org/2000/svg';

    public function appendTo(Document $document);
  }
}

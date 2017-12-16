<?php

namespace Carica\CanvasGraphics\SVG {


  use Carica\CanvasGraphics\Utility\Options;

  class Document {

    public const XMLNS_SVG = Appendable::XMLNS_SVG;

    private $_document;
    private $_shapesNode;
    private $_styleNode;
    private $_definitionsNode;

    private $_width;
    private $_height;

    public const OPTION_BLUR = 'blur';
    public const OPTION_XML_DECLARATION = 'xml_declaration';
    public const OPTION_SVG_VERSION = 'svg_version';
    public const OPTION_FORMAT_OUTPUT = 'format_output';

    private static $_optionDefaults = [
      self::OPTION_BLUR => 0,
      self::OPTION_XML_DECLARATION => FALSE,
      self::OPTION_SVG_VERSION => FALSE,
      self::OPTION_FORMAT_OUTPUT => FALSE
    ];
    private $_options;

    public function __construct($width, $height, array $options = []) {
      $this->_width = $width;
      $this->_height = $height;
      $this->_options = new Options(self::$_optionDefaults, $options);
    }

    public function getDocument() {
      if (NULL === $this->_document) {
        $this->_document = $document = new \DOMDocument();
        $document->formatOutput = $this->_options[self::OPTION_FORMAT_OUTPUT];
        $document->appendChild(
          $svg = $document->createElementNS(self::XMLNS_SVG, 'svg')
        );
        if ($this->_options[self::OPTION_SVG_VERSION]) {
          $svg->setAttribute('varsion', '1.1');
        }
        $svg->setAttribute('width', $this->_width);
        $svg->setAttribute('height', $this->_height);
      }
      return $this->_document;
    }

    public function getShapesNode() {
      if (NULL === $this->_shapesNode) {
        $document = $this->getDocument();
        $svg = $document->documentElement;
        $svg->appendChild(
          $group = $document->createElementNS(self::XMLNS_SVG, 'g')
        );
        $definitions = $this->getDefinitionsNode();
        $blurDeviation = $this->_options[self::OPTION_BLUR];
        if ($blurDeviation > 0) {
          $definitions->appendChild(
            $filter = $document->createElementNS(self::XMLNS_SVG, 'filter')
          );
          $filter->setAttribute('id', 'b');
          $filter->appendChild(
            $blur = $document->createElementNS(self::XMLNS_SVG, 'feGaussianBlur')
          );
          $blur->setAttribute('stdDeviation', $blurDeviation);
          $group->setAttribute('filter', 'url(#b)');
        }
        $this->_shapesNode = $group;
      }
      return $this->_shapesNode;
    }

    public function getDefinitionsNode() {
      if (NULL === $this->_definitionsNode) {
        $document = $this->getDocument();
        $document->documentElement->insertBefore(
          $this->_definitionsNode = $document->createElementNS(self::XMLNS_SVG,'defs'),
          $document->documentElement->firstChild
        );
      }
      return $this->_definitionsNode;
    }

    public function appendStyle($selector, array $properties) {
      if (NULL === $this->_styleNode) {
        $document = $this->getDocument();
        $document->documentElement->insertBefore(
          $this->_styleNode = $document->createElementNS(self::XMLNS_SVG, 'style', "\n"),
          $document->documentElement->firstChild
        );
        $this->_styleNode->setAttribute('type', 'text/css');
      }
      $lf = '';
      $indent = ' ';
      $propertyIndent = ' ';
      if ($this->_options[self::OPTION_FORMAT_OUTPUT]) {
        $lf = "\n";
        $indent = '    ';
        $propertyIndent = $indent.'  ';
      }
      $style = $indent.$selector.' {'.$lf;
      foreach ($properties as $name=>$value) {
        if (!empty($value)) {
          $style .= $propertyIndent.$name.': '.$value.';'.$lf;
        }
      }
      $style .= $indent.'}'.$lf;
      $this->_styleNode->textContent .= $style;
    }

    public function getWidth() {
      return $this->_width;
    }

    public function getHeight() {
      return $this->_height;
    }

    public function append(Appendable $shape) {
      $shape->appendTo($this);
    }

    public function getXML() {
      $document = $this->getDocument();
      if ($this->_options[self::OPTION_XML_DECLARATION]) {
        return $document->saveXML();
      }
      return $document->saveXML($document->documentElement);
    }
  }
}



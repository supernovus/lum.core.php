<?php

namespace Lum\Data;

use \Lum\Exception;

/**
 * A trait to add simple XML Input and conversion methods to data objects.
 */
trait InputXML
{
  /**
   * A method to load a SimpleXMLElement into your data.
   * 
   * It may load a sub-class such as SimpleDOM as well.
   */
  abstract public function load_simple_xml($simplexml, $opts=[]);

  // Load an XML string.
  public function load_xml_string ($string, $opts=Null)
  {
    $simplexml = new \SimpleXMLElement($string);
    return $this->load_simple_xml($simplexml, $opts);
  }

  // Load a DOMNode object.
  public function load_dom_node ($dom, $opts=Null)
  {
    $simplexml = simplexml_import_dom($dom);
    return $this->load_simple_xml($simplexml);
  }

  // Load a SimpleDOM object.
  public function load_simple_dom ($simpledom, $opts=null)
  { // SimpleDOM is an extension of SimpleXML, so just do it right.
    return $this->load_simple_xml($simpledom, $opts);
  }

}
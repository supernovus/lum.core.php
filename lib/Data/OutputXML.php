<?php

namespace Lum\Data;

use \Lum\Exception;

/**
 * A trait to add simple XML Output and conversion methods to data objects.
 */
trait OutputXML
{
  /**
   * A method to serialize your data as a SimpleXMLElement.
   * 
   * It may return a sub-class such as SimpleDOM as well.
   */
  abstract public function to_simple_xml($opts=[]);

  // Return a DOMElement
  public function to_dom_element ($opts=Null)
  {
    $simplexml = $this->to_simple_xml($opts);
    $dom_element = dom_import_simplexml($simplexml);
    return $dom_element;
  }

  // Return a DOMDocument
  public function to_dom_document ($opts=Null)
  {
    $dom_element = $this->to_dom_element($opts);
    $dom_document = $dom_element->ownerDocument;
    return $dom_document;
  }

  // Return a SimpleDOM object, must have SimpleDOM library loaded first.
  public function to_simple_dom ($opts=null)
  {
    if (function_exists('simpledom_import_simplexml'))
    { // SimpleDOM 2.x used a global function to do this.
      $simplexml = $this->to_simple_xml($opts);
      return simpledom_import_simplexml($simplexml);
    }
    elseif (is_callable('\\SimpleDOM::fromSimpleXML'))
    { // SimpleDOM 3.x uses a static class method instead.
      $simplexml = $this->to_simple_xml($opts);
      return \SimpleDOM::fromSimpleXML($simplexml);
    }
    else
    { // No SimpleDOM loaded.
      $fatal = isset($opts, $opts['fatal']) ? (bool)$opts['fatal'] : true;
      $msg = "SimpleDOM not found, but to_simple_dom() was called";
      if ($fatal)
      {
        throw new Exception($msg);
      }
      else
      {
        error_log($msg);
        return null;
      }
    }
  }

  // Return an XML string.
  public function to_xml ($opts=Null)
  {
    $simplexml = $this->to_simple_xml($opts);
    $xmlstring = $simplexml->asXML();
    if (isset($opts, $opts['reformat']) && $opts['reformat'])
    {
      $dom = new \DOMDocument('1.0');
      $dom->preserveWhiteSpace = False;
      $dom->formatOutput = True;
      $dom->loadXML($xmlstring);
      return $dom->saveXML();
    }
    else
    {
      return $xmlstring;
    }
  }


}
<?php

namespace Lum\Data;

use \Lum\Exception;

/**
 * A trait to support support methods for building XML documents.
 */
trait BuildXML
{
  /**
   * Assuming the get_classname() method from \Lum\Meta\ClassInfo;
   * But you could technically supply your own method.
   */
  abstract public function get_classname($object=null);

  /**
   * Get a SimpleXMLElement to populate from a data object.
   *
   * This is generally not required to be called by outside sources, but
   * is meant for use in to_simple_xml() methods like the XML trait requires.
   *
   * @todo Document this and it's bazillion options properly.
   */
  public function get_simple_xml_element (array $opts)
  {
    if (isset($opts['element']))
    {
      if ($opts['element'] instanceof \SimpleXMLElement)
      {
        $xml = $opts['element'];
      }
      elseif (is_string($opts['element']))
      {
        $xml = new \SimpleXMLElement($opts['element']);
      }
      else
      {
        throw new Exception("Invalid XML passed.");
      }
    }
    elseif (isset($opts['parent_element']))
    {
      if ($opts['parent_element'] instanceof \SimpleXMLElement)
      {
        $parent = $opts['parent_element'];
      }
      elseif (is_string($opts['parent_element']))
      {
        $parent = new \SimpleXMLElement($opts['parent_element']);
      }
      else
      {
        throw new Exception("Invalid parent XML passed.");
      }

      if (isset($opts['child_element']))
      {
        $tag = $opts['child_element'];
      }
      elseif (isset($opts['default_tag']))
      {
        $tag = $opts['default_tag'];
      }
      else
      {
        $tag = $this->get_classname();
      }

      $xml = $parent->addChild($tag);
    }
    elseif (isset($opts['default_element']))
    {
      $defxml = $opts['default_element'];
      if ($defxml instanceof \SimpleXMLElement)
      {
        $xml = $defxml;
      }
      elseif (is_string($defxml))
      {
        $xml = new \SimpleXMLElement($defxml);
      }
      else
      {
        throw new Exception("Invalid default XML passed.");
      }
    }
    elseif (isset($opts['default_tag']))
    {
      $defxml = '<'.$opts['default_tag'].'/>';
      $xml = new \SimpleXMLElement($defxml);
    }
    else
    {
      $defxml = '<'.$this->get_classname().'/>';
      $xml = new \SimpleXMLElement($defxml);
    }
    return $xml;
  }

}
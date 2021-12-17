<?php

namespace Lum\Data;

/**
 * A trait to add simple format detection to data objects.
 */
trait DetectType
{
  /**
   * Attempt to detect the format of data, and return a simple string for it.
   *
   * The default version supports the following formats:
   *
   *   array             =>  'array'
   *   SimpleDOM         =>  'simple_dom'
   *   SimpleXMLElement  =>  'simple_xml'
   *   DOMNode           =>  'dom_node'
   *
   *   string            =>  see detect_strinc_type()
   *
   * If you use this in a base class, then sub-classes can override it and
   * extend it with additional supported formats.
   *
   * @param mixed $data  The data we're trying to detect the format of.
   *
   * @return ?string  The detected format, or null if no valid format detected.
   */
  protected function detect_data_type ($data)
  {
    if (is_array($data))
    {
      return 'array';
    }
    elseif (is_string($data))
    {
      return $this->detect_string_type($data);
    }
    elseif (is_object($data))
    {
      if ($data instanceof \SimpleDOM)
      {
        return 'simple_dom';
      }
      elseif ($data instanceof \SimpleXMLElement)
      {
        return 'simple_xml';
      }
      elseif ($data instanceof \DOMNode)
      {
        return 'dom_node';
      }
    }
  }

  /**
   * Detect the type of string being loaded.
   *
   * This is very simplistic, you may want to override it.
   * The current formats it detects are based on the first non-whitespace
   * character in the string:
   *
   *   '<'          => 'xml_string'
   *   '{' or '['   => 'json'
   *
   * @param string $string  The string we're trying to detect.
   *
   * @return ?string  The detected format, or null if no valid format detected.
   */
  protected function detect_string_type (string $string)
  {
    $fc = substr(trim($string), 0, 1);
    if ($fc == '<')
    { // XML detected.
      return 'xml_string';
    }
    elseif ($fc == '[' || $fc == '{')
    { // JSON detected.
      return 'json';
    }
  }

}
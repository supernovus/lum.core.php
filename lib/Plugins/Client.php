<?php

namespace Lum\Plugins;

class Client
{
  /**
   * Parse an HTTP Accept-Language header and return a sorted array.
   */
  static function acceptLanguage ($langs=Null)
  {
    if (!isset($langs))
    {
      if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
      {
        $lang_string = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
      }
      else
      {
        return ['en'=>'1'];
      }
    }

    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $lang_string, $lang_parse);

    if (count($lang_parse[1]))
    {
      $langs = array_combine($lang_parse[1], $lang_parse[4]);

      foreach ($langs as $lang => $val)
      {
        if ($val === '') $langs[$lang] = '1';
      }

      arsort($langs, SORT_NUMERIC);
    }
    return $langs;
  }

  /**
   * Is the current browser Internet Explorer?
   */
  public static function is_ie ($ua=null)
  {
    if (!isset($ua))
    {
      $ua = $_SERVER['HTTP_USER_AGENT'];
    }
    if ($ua && preg_match('/MSIE|Trident/i', $ua))
    {
      return True;
    }
    return False;
  }

  /**
   * Returns a number if it is a valid IE version.
   *
   * Returns False if the browser is not IE.
   */
  public static function get_ie_ver ($ua=null)
  {
    if (!isset($ua))
    {
      $ua = $_SERVER['HTTP_USER_AGENT'];
    }
    
    if (!$ua) return False;

    preg_match('/MSIE (.*?);/', $ua, $matches);
    if (count($matches) < 2)
    {
      $trident = '/Trident\/\d{1,2}.\d{1,2}; rv:([0-9]*)/';
      preg_match($trident, $ua, $matches);
    }

    if (count($matches) > 1)
    {
      $version = $matches[1];
      return $version;
    }

    return False;
  }

} // class Browser

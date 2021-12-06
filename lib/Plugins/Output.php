<?php

namespace Lum\Plugins;

class Output
{

  public static function json ($ieSupport=false)
  {
    if ($ieSupport && Client::is_ie())
    { // A horrible hack for IE.
      header('Content-Type: text/plain');
    }
    else
    { // The standard JSON MIME type.
      header('Content-Type: application/json');
    }
  }

  public static function xml ($text=false)
  {
    if ($text)
    {
      header('Content-Type: text/xml');
    }
    else
    {
      header('Content-Type: application/xml');
    }
  }

  public static function nocache ($expires=false)
  {
    header('Cache-Control: no-cache, must-revalidate');
    if ($expires)
    {
      if (!is_string($expires))
      { // Use a default that is well expired.
        $expires = 'Thu, 22 Jun 2000 18:45:00 GMT';
      }
      header("Expires: $expires");
    }
  }

} // class Output

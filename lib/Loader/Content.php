<?php

namespace Lum\Loader;

/** 
 * A Loader Type Trait for returning parsed PHP content.
 */
trait Content
{
  public function load ($class, $data=Null)
  {
    $file = $this->find_file($class);
    if (isset($file))
    {
      $output = \Lum\Core::get_php_content($file, $data);
      return $output;
    }
    else
      throw new \Lum\Exception("Attempt to load invalid PHP file: '$file' (from: '$class')");
  }
}


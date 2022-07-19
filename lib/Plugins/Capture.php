<?php

/**
 * Capture script output. 
 */

namespace Lum\Plugins;

class Capture
{
  public function start ()
  {
    ob_start();
  }
  public function end ()
  {
    $content = ob_get_contents();
    @ob_end_clean();
    return $content;
  }
}

<?php

namespace Lum\Plugins;

/**
 * Capture script output. 
 */
class Capture
{
  /**
   * Start output buffer capture.
   */
  public function start ()
  {
    ob_start();
  }

  /**
   * End capturing output buffer.
   * @return string|false
   */
  public function end ()
  {
    $content = ob_get_contents();
    @ob_end_clean();
    return $content;
  }
}

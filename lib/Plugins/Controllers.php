<?php

namespace Lum\Plugins;

class Controllers extends Instance 
{
  /**
   * Initialize the "screens" and "layouts" loaders,
   * using our recommended default folders.
   *
   * If you don't want any folders set, pass False.
   */
  public function use_screens ($defaults=True)
  {
    $core = \Lum\Core::getInstance();
    $core->layouts = 'views';
    $core->screens = 'views';
    if ($defaults)
    {
      $viewroot = $core['viewroot'];
      if (!isset($viewroot))
        $viewroot = 'views';
      $core->layouts->addDir("$viewroot/layouts");
      $core->screens->addDir("$viewroot/screens");
    }
  }
}


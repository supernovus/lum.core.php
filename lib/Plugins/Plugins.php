<?php

namespace Lum\Plugins;
use \Lum\Loader;

class Plugins
{
  use Loader\Namespaces, Loader\Instance 
  {
    Loader\Instance::load as load_class;
  }

  public function __construct ($opts=[])
  {
    if (isset($opts['namespace']) && is_array($opts['namespace']))
    {
      $this->namespace = $opts['namespace'];
    }
    else
    {
      $this->namespace = ["\\Lum\\Plugins"];
    }

    // We don't allow nested namespaces for plugins.
    $this->nestedNames = false;

    // We enable case sensitive names.
    $this->caseSensitive = true;
  }

  public function load ($class, $opts=[])
  {
    $core = \Lum\Core::getInstance();
    $plugin = $this->load_class($class, $opts);

    if (isset($opts['as']))
      $name = $opts['as'];
    else
      $name = $class;

    $core->_add_plugin($name, $plugin);
  }

}

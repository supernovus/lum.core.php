<?php

namespace Lum;

/** 
 * The Lum Core. 
 *
 * It's a simple singleton object that offers plugins to help make building
 * PHP apps simple. Does not use the regular PHP constructor.
 * Instead you do \Lum\Core::getInstance().
 */
class Core extends Util implements \ArrayAccess
{
  protected static $__instance;

  public $allowOverwrite = false;

  protected $exceptionHandler; // Exception handler.

  protected $lib     = [];    // Library objects.
  protected $methods = [];    // Extension methods (callbacks.)
  protected $opts    = [];    // Options passed to the constructor.

  /**
   * Get the current Lum Core instance, or create an instance.
   *
   * @param array $opts  Options to pass to instance (optional).
   *
   * @return Lum\Core  The Lum Core instance.
   *
   * @throws Lum\Exception  If no instance has been created yet.
   */
  public static function getInstance ($opts=null)
  {
    if (!isset(static::$__instance))
    {
      static::$__instance = new static($opts);
    }
    if (isset($opts))
    {
      static::$__instance->loadOpts($opts, true);
    }
    return static::$__instance;
  }

  /**
   * Internal constructor used by createInstance to build the object.
   */
  protected function __construct ($opts)
  {
    // Initialize the Plugins plugin. Will be used to load all other plugins.
    $plugopts = isset($opts) 
      ? (isset($opts['plugins']) ? $opts['plugins'] : $opts) 
      : [];
    $this->lib['plugins'] = new \Lum\Plugins\Plugins($plugopts);
  }

  /**
   * Set an Exception handler that will be used by your app whenever
   * an exception is thrown.
   *
   * @param Callable $handler  The exception handler to set.
   * @param bool $overwrite    (Optional, default false) Overwrite existing?
   * @return mixed  If an old handler was set and we overwrote it, the old
   *                handler will be returned. Otherwise we return null/void.
   */
  public function setExceptionHandler (Callable $handler, $overwrite=false)
  {
    if ($overwrite || !isset($this->exceptionHandler))
    {
      $oldhandler = $this->exceptionHandler;
      $this->exceptionHandler = $handler;
      set_exception_handler($handler);
      return $oldhandler;
    }
  }

  /**
   * Clear the currently set Exception handler.
   *
   * @return mixed  The Exception handler that we cleared if it was set.
   */
  public function clearExceptionHandler ()
  {
    $handler = $this->exceptionHandler;
    unset($this->exceptionHandler);
    set_exception_handler(null);
    return $handler;
  }

  /**
   * Load options.
   *
   * @param mixed $opts  An array of options, or a path to a JSON file.
   * @param bool $overwrite=false Should we overwrite existing options?
   */
  public function loadOpts ($opts, $overwrite=false)
  {
    if (is_array($opts))
    {
      foreach ($opts as $key => $val)
      {
        if ($overwrite || !isset($this->opts[$key]))
        {
          $this->opts[$key] = $val;
        }
      }
    }
    elseif (is_string($opts))
    {
      static::load_opts_from($opts, $this->opts, $overwrite);
    }
  }

  /**
   * Get all currently set options.
   */
  public function getOpts ()
  {
    return $this->opts;
  }

  // Not meant for use outside of the Plugins plugin.
  public function _add_plugin ($name, $plugin)
  {
    if (isset($this->lib[$name]) && !$this->allowOverwrite)
    {
      throw new Exception("Cannot overwrite '$name' plugin.");
    }
    if (!is_object($plugin))
    {
      throw new Exception("Plugin must be an object.");
    }
    $this->lib[$name] = $plugin;
  }

  /**
   * See if we have a library loaded already.
   */
  public function __isset ($offset)
  {
    return isset($this->lib[$offset]);
  }

  /**
   * Use like: $core->targetname = 'pluginname';
   * or:       $core->targetname = ['plugin'=>$name, ...];
   */
  public function __set($offset, $value)
  {
    if ($offset == 'plugins')
    {
      throw new Exception("Cannot overwrite 'plugins' plugin.");
    }
    if (is_array($value))
    {
      $opts = $value;
      if (isset($value['plugin']))
      {
        $class = $value['plugin'];
        $opts['as'] = $offset;
      }
      else
      {
        $class = $offset;
      }
    }
    elseif (is_string($value))
    {
      $class = $value;
      $opts  = ['as'=>$offset];
    }
    else
    {
      throw new Exception("Unsupported library load value");
    }

    $this->lib['plugins']->load($class, $opts);
  }

  /**
   * Not recommended, but no longer forbidden, except for 'plugins'.
   */
  public function __unset ($offset)
  {
    if ($offset == 'plugins')
    {
      throw new Exception("Cannot remove the 'plugins' plugin.");
    }
    unset($this->lib[$offset]);
  }

  /**
   * Get a library object from our collection.
   * This supports autoloading plugins using the 'plugins' plugin.
   */
  public function __get ($offset)
  {
    if (isset($this->lib[$offset]))
    {
      return $this->lib[$offset];
    }
    elseif ($this->lib['plugins']->is($offset))
    { // A plugin matched, let's load it.
      $this->lib['plugins']->load($offset);
      return $this->lib[$offset];
    }
    else
    {
      throw new Exception("Invalid Lum plugin called.");
    }
  }

  /* The ArrayAccess interface is mapped to the options. */

  /**
   * Does the option exist?
   */
  public function offsetExists ($path): bool
  {
    $get = $this->offsetGet($path);
    if (isset($get))
      return True;
    else
      return False;
  }

  /**
   * Set an option.
   */
  public function offsetSet ($path, $value): void
  {
    $tree = explode('.', $path);
    $data = &$this->opts;
    $key  = array_pop($tree);
    foreach ($tree as $part)
    {
      if (!isset($data[$part]))
        $data[$part] = [];
      $data = &$data[$part];
    }
    $data[$key] = $value;
  }

  /**
   * Unset an option.
   */
  public function offsetUnset ($path): void
  {
    $tree = explode('.', $path);
    $data = &$this->opts;
    $key  = array_pop($tree);
    foreach ($tree as $part)
    {
      if (!isset($data[$part]))
        return; // A part of the tree doesn't exist, we're done.
      $data = &$data[$part];
    }
    if (isset($data[$key]))
      unset($data[$key]);
  }

  /**
   * Get an option based on a path.
   */
  public function offsetGet ($path): mixed
  {
    $find = explode('.', $path);
    $data = $this->opts;
    foreach ($find as $part)
    {
      if (is_array($data) && isset($data[$part]))
        $data = $data[$part];
      else
        return Null;
    }
    return $data;
  }

  /**
   * Add an extension method. 
   *
   * This function allows extensions to add a method to the Lum object.
   * 
   * @param string $name      The name of the method we are adding.
   * @param mixed  $callback  A callback, with exceptions, see below.
   *
   * If the callback parameter is a string, then the normal PHP callback
   * rules are ignored, and the string is assumed to be the name of a
   * library object that provides the given method (the method in the library
   * must be the same name, and must be public.)
   *
   * Class method calls, object method calls and closures are handled
   * as per the standard PHP callback rules.
   */
  public function addMethod ($name, $callback)
  {
    $this->methods[$name] = $callback;
  }

  /**
   * Remove an extension method.
   */
  public function removeMethod ($name)
  {
    unset($this->methods[$name]);
  }

  /**
   * The __call() method looks for extension methods, and calls them.
   */
  public function __call ($method, $arguments)
  {
    // First we check for extension methods.
    if (isset($this->methods[$method]))
    {
      if (is_string($this->methods[$method]))
      { // A string is assumed to be the name of a library.
        // We don't support plain function callbacks.
        $libname  = $this->methods[$method];
        $libobj   = $this->lib[$libname];
        $callback = [$libobj, $method];
      }
      else
      { // Anything else is considered the callback itself.
        // Which can be a class method call, object method call or closure.
        $callback = $this->methods[$method];
      }
      // Now let's dispatch to the callback.
      return call_user_func_array($callback, $arguments);
    }

    // If we reached this far, we didn't find any methods.
    throw new Exception("Unhandled method '$method' called.");
  }

} // class Core


<?php

namespace Lum;

/**
 * A class to set up autoload rules.
 */
class Autoload
{
  protected static $instance;
  protected static $spl_autoload = false;
  protected static $psr4_autoload = false;

  protected $psr4_prefixes = [];
  protected $psr4_extensions = ['.php'];

  /**
   * Register autoloaders.
   *
   * @param array $opts  Named options.
   * 
   *  If omitted, uses ['spl'=>true, 'classroot'=>'lib'].
   *  If a string uses ['spl'=>true, 'classroot'=>$opts].
   *
   *  Supports the following options:
   *
   *   'spl'        (bool)          Register the spl_autoload autoloader.
   *   'classroot'  (array|string)  Base path(s) for sql_autoload.
   *   'psr4'       (bool)          Register a PSR-4 autoloader.
   *   'namespaces' (array)         Map of PSR-4 ['ns' => 'dir'] specifications.
   *   'extensions' (array)         Override default extensions ['.php'].
   *
   * @return mixed
   *
   *  If the 'psr4' option was set to true, this will return the Autoload
   *  instance (can also call \Lum\Autoload::getInstance() to get it.)
   *  Otherwise it returns null.
   */
  public static function register ($opts=null)
  {
    if (!isset($opts))
    {
      $opts = ['spl'=>true, 'classroot'=>'lib'];
    }
    elseif (is_string($opts))
    {
      $opts = ['spl'=>true, 'classroot'=>$opts];
    }
    elseif (!is_array($opts))
    {
      throw new Exception("Invalid Autoload::register() options passed");
    }

    $extensions = isset($opts['extensions']) ? $opts['extensions'] : null;

    if (isset($opts['spl']) && $opts['spl'])
    {
      $classroot = isset($opts['classroot']) ? $opts['classroot'] : null;
      static::register_spl($classroot, $extensions);
    }

    if (isset($opts['psr4']) && $opts['psr4'])
    {
      $namespaces = isset($opts['namespaces']) ? $opts['namespaces'] : null;
      return static::register_psr4($namespaces, $extensions);
    }
  }

  public static function register_psr4 ($namespaces=null, $extensions=null)
  {
    $instance = static::getInstance();
    if (!static::$psr4_autoload)
    {
      spl_autoload_register([$instance, 'loadClass']);
      static::$psr4_autoload = true;
    }
    if (isset($namespaces) && is_array($namespaces))
    {
      foreach ($namespaces as $ns => $dir)
      {
        $instance->addNamespace($ns, $dir);
      }
    }
    if (isset($extensions) && is_array($extensions))
    {
      foreach ($extensions as $ext)
      {
        $instance->addExtension($ext);
      }
    }
    return $instance;
  }

  public static function register_spl ($classroot=null, $extensions=null)
  {
    if (!static::$spl_autoload)
    { // We haven't registered the spl autoloader yet.
      spl_autoload_register('spl_autoload');
      if (!isset($extensions))
      { // Default is '.php'
        spl_autoload_extensions('.php');
      }
      static::$spl_autoload = true;
    }
    if (isset($classroot))
    {
      if (is_array($classroot))
      {
        $classroot = join(PATH_SEPARATOR, $classroot);
      }
      if (is_string($classroot))
      {
        set_include_path(get_include_path().PATH_SEPARATOR.$classroot);
      }
    }
    if (isset($extensions))
    {
      if (is_array($extensions))
      {
        $extensions = join(',', $extensions);
      }
      if (is_string($extensions))
      {
        spl_autoload_extensions($extensions);
      }
    }
  }

  /**
   * Unregister sql_autoload.
   */
  public static function unregister_spl ()
  {
    if (static::$spl_autoload)
    {
      spl_autoload_unregister('spl_autoload');
      static::$spl_autoload = false;
    }
  }

  /**
   * Unregister our PSR-4 autoload.
   */
  public static function unregister_psr4 ()
  {
    if (static::$psr4_autoload)
    {
      $instance = static::getInstance();
      spl_autoload_unregister([$instance, 'loadClass']);
    }
  }

  /**
   * Get a PSR-4 Autoloader instance.
   */
  public static function getInstance ()
  {
    if (!isset(static::$instance))
    {
      static::$instance = new static();
    }
  }

  protected function __construct () {}

  /**
   * Add a namespace => basedir mapping to a PSR-4 Autoloader instance.
   *
   * @param string $prefix The namespace prefix.
   * @param string $basedir The base directory for classes in the namespace.
   * @param bool $prepend If true, prepend the base directory.
   * @return void
   */
  public function addNamespace ($prefix, $basedir, $prepend=false)
  {
    // Normalize namespace prefix.
    $prefix = trim($prefix, '\\') . '\\';

    // Normalize base directory.
    $basedir = rtrim($basedir, DIRECTORY_SEPARATOR) . '/';

    if (!isset($this->psr4_prefixes[$prefix]))
    {
      $this->psr4_prefixes[$prefix] = [];
    }

    if (!in_array($basedir, $this->psr4_prefixes[$prefix]))
    {
      if ($prepend)
      {
        array_unshift($this->psr4_prefixes[$prefix], $basedir);
      }
      else
      {
        $this->psr4_prefixes[$prefix][] = $basedir;
      }
    }
  }

  public function removeNamespace ($prefix, $basedir=null)
  {
    if (isset($this->psr4_prefixes[$prefix]))
    {
      if (isset($basedir))
      {
        $pos = array_search($basedir, $this->psr4_prefixes[$prefix]);
        if ($pos !== false)
        {
          array_splice($this->psr4_prefixes[$prefix], $pos, 1);
        }
      }
      else
      {
        unset($this->psr4_prefixes[$prefix]);
      }
    }
  }

  public function addExtension ($ext, $prepend=false)
  {
    if (!in_array($ext, $this->psr4_extensions))
    {
      if ($prepend)
      {
        array_unshift($this->psr4_extensions, $ext);
      }
      else
      {
        $this->psr4_extensions[] = $ext;
      }
    }
  }

  public function removeExtension ($ext)
  {
    $pos = array_search($ext, $this->psr4_extensions);
    if ($pos !== false)
    {
      array_splice($this->psr4_extensions, $pos, 1);
    }
  }

  /**
   * Load the class.
   *
   * @param string $class The fully-qualified class name.
   * @return mixed The mapped file name on success, or boolean false otherwise.
   */
  public function loadClass ($class)
  {
    $prefix = $class;

    while (false !== $pos = strrpos($prefix, '\\'))
    {
      $prefix = substr($class, 0, $pos + 1);
      $relclass = substr($class, $pos + 1);
      $mapped = $this->loadMappedFile($prefix, $relclass);
      if ($mapped)
      {
        return $mapped;
      }

      // Remove trailing namespace separator.
      $prefix = rtrim($prefix, '\\');
    }

    return false;
  }

  protected function loadMappedFile ($prefix, $relclass)
  {
    if (!isset($this->psr4_prefixes[$prefix]))
    {
      return false;
    }

    foreach ($this->psr4_prefixes[$prefix] as $basedir)
    {
      foreach ($this->psr4_extensions as $ext)
      {
        $file = $basedir . str_replace('\\','/', $relclass) . $ext;
        if ($this->requireFile($file))
        {
          return $file;
        }
      }
    }

    return false;
  }

  protected function requireFile ($file)
  {
    if (file_exists($file))
    {
      require $file;
      return true;
    }
    return false;
  }

} // class Autoload


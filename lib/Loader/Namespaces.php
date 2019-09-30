<?php

namespace Lum\Loader;

/** 
 * A Loader Provider Trait that looks for PHP classes in Namespaces.
 *
 * This expects that an Autoloader is in use.
 */
trait Namespaces
{
  /**
   * You MUST specify at least one namespace, and it MUST be an array.
   */
  protected $namespace = [];

  /**
   * If using a PSR-4 autoloader, you may need this if you want to be able
   * to have $lum->controllers->load('subsection.controller') load the
   * \MyApp\Subsection\Controller class. This is due to the case-sensitive
   * nature of PSR-4 autoloaders vs the SPL autoloader which isn't.
   */
  public $caseSensitive = false;

  /**
   * If this is set to false, we don't allow for nested classes.
   */
  public $nestedNames = true;

  /** 
   * A default constructor. If you override it, don't forget to
   * call the __construct_namespace() method.
   */
  public function __construct ($opts=[])
  {
    $this->__construct_namespace($opts);
  }

  /**
   * A constructor call that builds our namespace.
   */
  public function __construct_namespace ($opts=[])
  {
    if (isset($opts['namespace']) && is_array($opts['namespace']))
    {
      $this->namespace = $opts['namespace'];
    }
  }

  public function is ($classname)
  {
    $class = $this->find_class($classname);
    return isset($class);
  }

  public function find_class ($classname)
  {
    // Replace '.' with '\\' for nested module names.
    $classnames = $this->get_classnames($classname);
    foreach ($this->namespace as $ns)
    {
      foreach ($classnames as $classname)
      {
        $class = $ns . "\\" . $classname;
        if (class_exists($class))
        {
          return $class;
        }
      }
    }
  }

  protected function get_classnames ($classname)
  {
    if (!$this->nestedNames)
    { // No nesting allowed, so no replacment of characters will be done.
      $classnames = [$classname];
      if ($this->caseSensitive)
      {
        $classnames[] = ucfirst(strtolower($classname));
      }
    }
    else
    { // The default is the class name as passed with '.' changed to '\'.
      $classnames = [str_replace('.', "\\", $classname)]; 
      if ($this->caseSensitive)
      { // If case sensitive is enabled, add a version compatible with PSR-4.
        $classpaths = explode('.', $classname);
        $classname = '';
        foreach ($classpaths as $c => $classpath)
        {
          if ($c > 0) $classname .= "\\";
          $classname .= ucfirst(strtolower($classpath));
        }
        $classnames[] = $classname;
      }
    }
    return $classnames;
  }

  public function find_file ($classname)
  {
    $class = $this->find_class($classname);
    if (isset($class))
    {
      $reflector = new ReflectionClass($class);
      return $reflector->getFileName();
    }
  }

  // Add namespaces to search.
  public function addNS ($ns, $top=False)
  {
    if ($top)
    {
      if (is_array($ns))
        array_splice($this->namespace, 0, 0, $ns);
      else
        array_unshift($this->namespace, $ns);
    }
    else
    {
      if (is_array($ns))
        array_splice($this->namespace, -1, 0, $ns);
      else
        $this->namespace[] = $ns;
    }
  }

}


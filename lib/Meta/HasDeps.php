<?php

namespace Lum\Meta;

/**
 * A trait that adds a generic ability to test for other traits with
 * initialization methods and call the methods in a particular order.
 *
 * It uses the Deps class to do the actual dependency tracking.
 *
 * @uses HasProps
 *
 * A few optional properties that your class can define:
 *
 *
 * 
 */
trait HasDeps
{
  use HasProps;

  // Will be used to store the current dep group.
  protected $_current_dep_group = null;

  protected function _dep_group(array $opts=[], ?array $args=null): Dep_Group
  {
    if (isset($args))
    { 
      if (isset($opts['args']) && is_array($opts['args']))
      {
        $opts['args'] = array_merge($opts['args'], $args);
      }
      $opts['args'] = $args;
    }

    if (!isset($opts['closure']))
    {
      $opts['closure'] = function(string $method, array $args): mixed
      {
        $callable = [$this, $method];
        if (is_callable($callable))
        {
          return call_user_func_array($callable, $args);
        }
        else
        {
          throw new No_Dep_Method("No such method '$method'");
        }
      };
    }

    $dg = new Dep_Group($opts);

    if (isset($opts['deps']) && is_array($opts['deps']))
    {
      $fullname = false;
      $deps = $opts['deps'];
    }
    elseif (isset($opts['deps_prop']))
    {
      $fullname = false;
      $deps = $this->get_prop($opts['deps_prop']);
    }
    elseif (isset($opts['prefix']) || isset($opts['postfix']))
    {
      $fullname = true;
      $prefix = isset($opts['prefix']) ? $opts['prefix'] : '';
      $postfix = isset($opts['postfix']) ? $opts['postfix'] : '';
      $regex = "/$prefix(\w+)$postfix/i";
      $deps = preg_grep($regex, get_class_methods($this));
    }

    if (isset($deps))
    { // Let's start the process.
      $this->_current_dep_group = $dg;
      $this->needs($deps, null, $fullname);
    }

    return $dg;
  }

  public function needs (
    string|array $depname, 
    ?array $args=null, 
    bool $fullname=false): void
  {
    if (!isset($this->_current_dep_group))
    {
      throw new No_Dep_Group();
    }
    $this->_current_dep_group->run($depname, $args, $fullname, false);
  }

  public function wants (
    string|array $depname, 
    ?array $args=null, 
    bool $fullname=false): bool|array
  {
    if (!isset($this->_current_dep_group))
    {
      throw new No_Dep_Group();
    }
    return $this->_current_dep_group->run($depname, $args, $fullname, true);
  }

}

/**
 * This is an internal class that you should probably never need to use.
 * It's used by the dep_group(), needs(), and wants() methods in the trait.
 */
class Dep_Group
{
  use SetProps;

  protected array  $called  = [];
  protected string $prefix  = '';
  protected string $postfix = '';
  protected array  $args    = [];

  protected ?\Closure $closure = null;

  public function __construct(array $opts=[])
  {
    error_log("Dep_Group::__construct(".json_encode(array_keys($opts)).")");
    $this->set_props($opts);
    if (!isset($this->closure))
    { // That's not valid, we cannot continue without a closure.
      throw new No_Dep_Closure();
    }
  }

  public function run (
    string|array $depname, 
    ?array $args, 
    bool $fullname, 
    bool $nofail): bool|array
  {
    if (is_null($args))
    { // Use the defaults.
      $args = $this->args;
    }

    if (is_array($depname))
    { 
      $res = [];
      foreach ($depname as $dep)
      {
        $res[$dep] = $this->run($dep, $args, $fullname, $nofail);
      }
      return $res;
    }

    if ($fullname)
    {
      $method = $depname;
    }
    else
    {
      $method = $this->prefix.$depname.$this->postfix;
    }
    
    if (isset($this->called[$method]))
    { // It's been called already return the result we had from that.
      return $this->called[$method];
    }

    try
    {
      $cl = $this->closure;
      $cl($method, $args);
      return true;
    }
    catch (\Throwable $e)
    { // We failed the call the method.
      if ($nofail)
      { 
        return false;
      }
      else
      {
        throw $e;
      }
    }
  }

}

class No_Dep_Group extends \Lum\Exception
{
  protected $message = 'No current dep group set';
}

class No_Dep_Closure extends \Lum\Exception
{
  protected $message = 'No closure passed to dep group';
}

class No_Dep_Method extends \Lum\Exception {}
<?php

namespace Lum\Meta;

use \Closure;
use \Lum\{Exception,WrappedException};

/**
 * A trait that adds a generic ability to test for other traits with
 * initialization methods and call the methods in a particular order.
 *
 * @uses HasProps
 * 
 */
trait HasDeps
{
  use HasProps;

  protected readonly Dep_Groups $_dep_groups;

  /**
   * The method to create or retrieve a trait dependency group.
   *
   * @param string $id    The 'id' of the group, can be referred to later.
   *
   * @param array|bool $opts  Options that determine what we want to do:
   *
   *   If $opts is an array, then we're creating a new dep group. 
   *   If a dep group with the specified $id has already been created, 
   *   an exception will be thrown.
   *
   *   In addition to any constructor properties supported by Dep_Group,
   *   there are a few additional options specific to this method, all of
   *   which are completely optional and intended for advanced use only:
   *
   *     `auto_deps` (bool) Run initial dependencies? (Default is true).
   *     `deps` (array) A list of initial dependencies to run.
   *     `deps_prop` (string) A property to get the initial dependencies from.
   *
   *   If $opts['auto_deps'] is not false, and neither `deps` nor `deps_prop`
   *   was set in the $opts, but the `prefix` or `postfix` constructor options
   *   were set, then we find any class methods with the prefix and/or postfix,
   *   and use them as as the initial dependencies.
   *
   *   If $opts is a boolean, then we're looking for an existing dep group,
   *   and will return it. If $opts is true, we will also set it as the
   *   current default dep group. If no dep group with the specified $id can
   *   be found, an exception will be thrown.   
   *
   * @param ?array $args  (Optional) Parameters used when creating new groups.
   *
   *   If $args is set, and $opts is set, but $opts['args'] isn't set, 
   *   then $opts['args'] will be set to $args.
   *
   *   If both $opts['args'] and $args are set, then the items from 
   *   $args will be appended to $opts['args'].
   *
   * @return Dep_Group  The created dependency group.
   *
   * @throws Invalid_Dep_Group  Couldn't find the dep group.
   */
  protected function _dep_group(
    string $id, 
    array|bool $opts, 
    ?array $args=null): Dep_Group
  {
    if (!isset($this->_dep_groups))
    { // We'll initialize this once, and only once as its read-only.
      $this->_dep_groups = new Dep_Groups($this);
    }

    if (is_bool($opts))
    { // Okay, this is retrieval mode.
      $dg = $this->_dep_groups[$id];
      if ($opts)
      { // Make the group the default.
        $dg->makeCurrent();
      }
      return $dg;
    }

    if (isset($this->_dep_groups[$id]))
    { // Duplicate group, I don't think so!
      throw new Existing_Dep_Group($id);
    }

    if (isset($args))
    { 
      if (isset($opts['args']) && is_array($opts['args']))
      {
        $opts['args'] = array_merge($opts['args'], $args);
      }
      else
      {
        $opts['args'] = $args;
      }
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
          throw new No_Dep_Method($method);
        }
      };
    }

    $dg = new Dep_Group($this->_dep_groups, $id, $opts);

    if (!isset($opts['auto_deps']) || $opts['auto_deps'])
    {
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
  
      if (!isset($deps) && isset($opts['prefix']) || isset($opts['postfix']))
      {
        $fullname = true;
        $prefix = isset($opts['prefix']) ? $opts['prefix'] : '';
        $postfix = isset($opts['postfix']) ? $opts['postfix'] : '';
        $regex = "/$prefix(\w+)$postfix/i";
        $deps = array_values(preg_grep($regex, get_class_methods($this)));
      }
  
      if (isset($deps))
      { // Let's start the process.
        $dg->makeCurrent();
        $this->needs($deps, null, $fullname);
      }
    }

    return $dg;
  }

  public function needs (
    string|array $dep, 
    ?array $args=null, 
    bool $fullname=false): void
  {
    $this->_dep_groups->getCurrent()->run($dep, $args, $fullname, false);
  }

  public function wants (
    string|array $dep, 
    ?array $args=null, 
    bool $fullname=false): bool|array
  {
    return $this->_dep_groups->getCurrent()->run($dep, $args, $fullname, true);
  }

}

/**
 * This is an internal class that will be used to store the dependency groups,
 * and keep track of which one is currently set as the default.
 */
class Dep_Groups extends \ArrayObject
{
  public readonly object $parent;

  public bool $debug = false;

  protected ?Dep_Group $current = null;

  public function __construct (object $parent)
  {
    $this->parent = $parent;
    if (property_exists($parent, 'debug') && is_bool($parent->debug))
    {
      $this->debug = $parent->debug;
    }
    parent::__construct();
  }

  /**
   * Get the current default dep group.
   */
  public function getCurrent(): ?Dep_Group
  {
    if (isset($this->current))
    {
      return $this->current;
    }
    else
    {
      throw new No_Dep_Group();
    }
  }

  /**
   * Set the current default dep group.
   *
   * @param ?Dep_Group|string  The dep group itself, or it's id.
   */
  public function setCurrent(Dep_Group|string $current): void
  {
    if (is_string($current))
    { // Group id was specified.
      if ($this->offsetExists($current))
      {
        $this->current = $this->offsetGet($current);
      }
      else
      {
        throw new Invalid_Dep_Group($current);
      }
    }
    else
    { // It was an object.
      $this->current = $current;
    }
  }

  /**
   * Clear the current default dep group.
   */
  public function clearCurrent(): void
  {
    $this->current = null;
  }

  // A version of offsetGet that is fatal for non-existent groups.
  public function offsetGet(mixed $key): mixed
  {
    if ($this->offsetExists($key))
    {
      return parent::offsetGet($key);
    }
    else
    {
      throw new Invalid_Dep_Group($key);
    }
  }

  // Do not use unset()
  public function offsetUnset(mixed $key): void
  {
    throw new Dep_Groups_Readonly();
  }

  // Do not try to assign values directly.
  public function offsetSet(mixed $key, mixed $value): void
  {
    throw new Dep_Groups_Readonly();
  }

  // This is the valid only way to add new groups.
  public function append(mixed $value): void
  {
    if (!($value instanceof Dep_Group))
    {
      throw new Invalid_Dep_Group(serialize($value));
    }
    if ($this->offsetExists($value->id))
    { // It's already here, no overwriting.
      throw new Dep_Groups_Readonly();
    }
    parent::offsetSet($value->id, $value);
  }

}

/**
 * This is an internal class that you should probably never need to use.
 * It's used by the dep_group(), needs(), and wants() methods in the trait.
 */
class Dep_Group
{
  use SetProps;

  public readonly string $id;
  protected Dep_Groups $parent;

  protected array  $called  = [];
  protected string $prefix  = '';
  protected string $postfix = '';
  protected array  $args    = [];

  protected ?Closure $closure = null;

  public function __construct(Dep_Groups $parent, string $id, array $opts)
  {
    if ($parent->debug)
    {
      error_log("Dep_Group::__construct(parent:"
      . get_class($parent->parent)
      . ",'$id'," 
      . json_encode($opts) 
      . ")");
    }

    // Set our explicit property arguments.
    $this->id = $id;
    $this->parent = $parent;

    // Set properties from options.
    $this->set_props($opts);
    if (!isset($this->closure))
    { // That's not valid, we cannot continue without a closure.
      throw new No_Dep_Param('closure');
    }

    // Finally, we add ourself to the parent.
    $parent->append($this);
  }

  public function makeCurrent()
  {
    $this->parent->setCurrent($this);
  }

  public function run (
    string|array $depname, 
    ?array $args, 
    bool $fullname, 
    bool $nofail): bool|array
  {
    if ($this->parent->debug)
    {
      $debug = 
      [
        'deps' => $depname,
        'args' => $args,
        'fullname' => $fullname,
        'nofail' => $nofail,
      ];
    }

    if (empty($args))
    { // Use the defaults.
      $args = $this->args;
      if (isset($debug))
      {
        $debug['_args'] = $debug['args'];
        $debug['args']  = $args;
      }
    }

    if (isset($debug))
    {
      error_log("Dep_Group::run(" . json_encode($debug) . ')');
    }

    if (is_array($depname))
    { 
      $res = [];
      for ($d = 0; $d < count($depname); $d++)
      {
        $dep = $depname[$d];
        if (str_contains($dep, ':'))
        { // We want a dep from another group.
          $depspec = explode(':', $dep);
          $gn = trim($depspec[0]);
          $og = $this->parent[$gn];
          if (!empty($depspec[1]))
          { // A single one.
            $od = trim($depspec[1]);
            $res[$od] = $og->run($od, $args, $fullname, $nofail);
          }
          else
          { // Okay, everything past this point belongs to the og.
            $subdeps = array_slice($depname, $d+1);
            $res[$gn] = $og->run($subdeps, $args, $fullname, $nofail);
            break; // We're done this loop.
          }
        }
        else
        { // Regular old deps.
          $res[$dep] = $this->run($dep, $args, $fullname, $nofail);
        }
      } // for deps
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

class Invalid_Dep_Group extends WrappedException
{
  protected function wrap ($msg)
  {
    return "Invalid dep group '$msg' specified";
  }
}

class Existing_Dep_Group extends WrappedException
{
  protected function wrap ($msg)
  {
    return "Existing dep group '$msg' cannot be overwritten";
  }
}

class Dep_Groups_Readonly extends Exception
{
  protected $message = 'You cannot overwrite or remove existing dep groups';
}

class No_Dep_Group extends Exception
{
  protected $message = 'No dep group to handle call was found';
}

class No_Dep_Param extends WrappedException
{
  protected function wrap ($msg)
  {
    return "Missing required '$msg' dep group parameter";
  }
}

class No_Dep_Method extends WrappedException 
{
  protected function wrap ($msg)
  {
    return "No such method '$msg'";
  }
}
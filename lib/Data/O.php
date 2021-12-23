<?php

namespace Lum\Data;

use \Lum\Exception;

/**
 * The absolute minimum data base class.
 */
abstract class O
{
  protected $parent;             // Will be set if we have a parent object.
  protected $data       = [];    // The actual data we represent.
  protected $newconst   = False; // If true, we enable the new constructor.
  protected $constprops = [];    // Constructor property items.
  protected $save_opts  = False; // Do we want to save our constructor opts?
  protected $data_opts;          // The saved opts, if the above is true.

  protected abstract function load_data ($data, $opts=[]);

  /**
   * The public constructor. The default version simply forwards to the
   * internal method, see below.
   */
  public function __construct ($mixed=Null, $opts=Null)
  {
    $this->__construct_data($mixed, $opts);
  }

  /**
   * Internal constructor method.
   *
   * Supports two forms, the original form has the data as the first
   * parameter, and a set of options as the second.
   *
   * The second form sends the options as the first parameter,
   * with a named 'data' option to specify the data. In this case,
   * the second parameter should be left off.
   */
  protected function __construct_data ($mixed=Null, $opts=Null)
  {
    if (is_null($opts))
    {
      if (is_array($mixed) && $this->newconst)
      {
        $opts  = $mixed;
        $mixed = isset($opts['data']) ? $opts['data'] : Null;
      }
      else
      {
        $opts = [];
      }
    }

    // Set the parent if it's defined.
    if (isset($opts['parent']))
    {
      $this->parent = $opts['parent'];
    }

    // Find any properties that we need to initialize.
    $props   = $this->constprops;
    $props[] = '__classid'; // Explicitly add __classid.
    foreach ($props as $popt => $pname)
    {
      // Positional entries have the same property name and option name.
      if (is_numeric($popt))
      {
        $popt = $pname;
      }

      if (property_exists($this, $pname) && isset($opts[$popt]))
      {
        $this->$pname = $opts[$popt];
      }
    }

    if (method_exists($this, 'data_init'))
    { // The data_init can set up pre-requisites to loading our data.
      // It CANNOT reference our data, as that has not been loaded yet.
      $this->data_init($opts);
    }

    // If we want the options saved for later, do it now.
    if ($this->save_opts)
    {
      $this->data_opts = $opts;
    }

    // How we proceed depends on if we have initial data.
    if (isset($mixed))
    { // Load the passed data.
      $loadopts = ['clear'=>False, 'prep'=>True, 'post'=>True];
      if (isset($opts['type']))
      {
        $loadopts['type'] = $opts['type'];
      }
      $this->load($mixed, $loadopts);
    }
    elseif (is_callable([$this, 'data_defaults']))
    { 
      if (!isset($opts['nodefaults']) || !$opts['nodefaults'])
      {
        // Set our default values.
        $this->data_defaults($opts);
      }
    }
  }

  // Return the parent object.
  public function parent ()
  {
    return $this->parent;
  }

  // Set our data to the desired structure.
  public function load ($data, $opts=[])
  { // If we set the 'clear' option, clear our any existing data.
    if (isset($opts['clear']) && $opts['clear'])
    {
      $this->clear();
    }
    $return = $this->load_data($data, $opts);
    if (isset($return) && $return !== True)
    {
      $this->data = $return;
    }
    // If we have set the 'post' option, call data_post().
    if (isset($opts['post']) && $opts['post'] 
      && method_exists($this, 'data_post'))
    {
      $this->data_post($opts);
    }
  }

  // Clear our data.
  public function clear ($opts=[])
  {
    $this->data = [];
  }

  // Spawn a new empty data object.
  public function spawn ($opts=[])
  {
    $copy = clone $this;
    $copy->clear();
    return $copy;
  }

}
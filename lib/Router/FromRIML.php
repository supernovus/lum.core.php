<?php

namespace Lum\Router;

/**
 * Take a RIML object, and generate a Lum Router configuration from it.
 *
 * Requires the 'riml/riml-parser' library from Composer.
 */
class FromRIML
{
  /**
   * A map of RIML property names to Lum Router parameter names.
   */
  const RIML_ROUTER_PROPS =
  [
    'name'          => 'name',
    'controller'    => 'controller',
    'method'        => 'action',
    'http'          => 'methods',
    'path'          => 'uri',
    'redirect'      => 'redirect',
    'redirectRoute' => 'redirect_is_route',
  ];

  /**
   * Force an array in the case of scalar values.
   */
  const RIML_ROUTER_ARRAY = ['methods'];

  public $auto_route_names = []; // Automatically generated route names.
  public $compiled = [];         // Compiled routes.

  public function compile ($riml)
  {
    if ($riml->hasRoutes())
    {
      foreach ($riml->getRoutes() as $route)
      {
        $this->compileRoute($route);
      }
    }
    return $this->compiled;
  }

  protected function compileRoute ($route, $rdef=[])
  {
    $isDefault = $route->defaultRoute;
    $addIt = $route->defaultRoute ? false : true;
    foreach (self::RIML_ROUTER_PROPS as $sname => $tname)
    {
      if ($sname == 'path')
      { // Special handling for path.
        if (isset($route->path))
        {
          $path = $route->path;
          if ($path === false)
          { // Directly using the parent path.
            continue;
          }
          if (strpos($path, '/') === false)
          {
            $path = "/$path/";
          }
          if (isset($rdef[$tname]))
          {
//            error_log("appending $path to {$rdef[$tname]}");
            $rdef[$tname] .= $path;
            $rdef[$tname] = str_replace('//', '/', $rdef[$tname]);
          }
          else
          {
//            error_log("setting $sname/$tname to $path");
            $rdef[$tname] = $path;
          }
        }
      }
      elseif (isset($route->$sname))
      {
        if (in_array($tname, self::RIML_ROUTER_ARRAY) && !is_array($route->$sname))
          $rdef[$tname] = [$route->$sname];
        else
          $rdef[$tname] = $route->$sname;
      }
    }
    if (!$route->virtual && !isset($rdef['name']))
    { // Auto-naming feature.
      if (isset($rdef['controller']))
        $name = $rdef['controller'];
      else
        $name = '';
      if (isset($rdef['action']))
      {
        $aname = str_replace($route->root->method_prefix, '_', $rdef['action']);
        if ($aname != "_default")
          $name .= $aname;
      }
      if (!isset($this->auto_route_names[$name]))
      {
        $rdef['name'] = $name;
        $this->auto_route_names[$name] = true;
      }
    }    
    if (!$route->virtual)
    {
      $this->compiled[] = [$rdef, $isDefault, $addIt];
    }
    unset($rdef['name']);
    if ($route->hasRoutes())
    {
      foreach ($route->getRoutes() as $subroute)
      {
        $this->compileRoute($subroute, $rdef);
      }
    }
  }

}

<?php

namespace Lum\Plugins;

/**
 * A custom Exception for Router errors.
 */
class RouterException extends \Lum\Exception {}

/**
 * Routing Dispatcher.
 * 
 * Matches routes based on rules.
 */

// The recognized HTTP methods.
const ROUTE_METHODS = ['GET','POST','PUT','DELETE','HEAD','PATCH','POKE','OPTIONS'];

const JSON_TYPE = 'application/json';
const XML_TYPE  = 'application/xml';

class Router
{
  protected $routes = [];  // A flat list of routes.
  protected $named  = [];  // Any named routes, for reverse generation.
  protected $default;      // The default route, must be explicitly set.

  protected $plugins = []; // Router plugin functions.

  public $base_uri = '';

  public $log   = False;   // Basic logging, tracks routing.
  // Advanced debugging log settings.
  public $debug =
  [
    'init'     => 0,
    'matching' => 0,
    'routing'  => 0,
    'building' => 0,
  ];

  public $default_filter = "([\w\-\~\.]+)"; // Used by default.

  public $current; // The most recently matched route context.

  public $populate_put_files = false; // PUT files added to _FILES global.

  public $populate_put_global = false; // Add _PUT global.

  public function known_routes ($showAll=false)
  {
    if ($showAll)
      return $this->routes;
    else
      return array_keys($this->named);
  }

  public function __construct ($opts=[])
  {
    if (isset($opts['base_uri']))
    {
      $this->base_uri($opts['base_uri']);
    }
    elseif (isset($opts['auto_prefix']) && $opts['auto_prefix'])
    {
      $this->auto_prefix();
    }

    if (isset($opts['populate_put_files']))
    {
      $this->populate_put_files = (bool)$opts['populate_put_files'];
    }
    if (isset($opts['populate_put_global']))
    {
      $this->populate_put_global = (bool)$opts['populate_put_global'];
    }

    if (isset($opts['extend']) && $opts['extend'])
    { // Register some helpers into the Lum object.
      $core = \Lum\Core::getInstance();
      $core->addMethod('dispatch',     [$this, 'route']);
      $core->addMethod('addRoute',     [$this, 'add']);
      $core->addMethod('addRedirect',  [$this, 'redirect']);
      $core->addMethod('addPage',      [$this, 'display']);
      $core->addMethod('setDefault',   [$this, 'setDefault']);
      $core->addMethod('loadRoutes',   [$this, 'load']);
    }
  }

  /**
   * Set debugging options from a configuration file.
   *
   * Does nothing if the config file doesn't exist.
   *
   * @param string $configfile  The debugging config file.
   */
  public function loadDebugConfig ($configfile)
  {
    if (file_exists($configfile))
    {
      $this->log = true;
      $router_debug = trim(file_get_contents($configfile));
      if (is_numeric($router_debug))
      { // A single numeric value is the old way of debugging.
        // This is very limited, and thus deprecated.
        // The 'routing' will be set to the value, all others will
        // be set to (value-1). Keep that in mind if still using this.
        $router_debug_routing = intval($router_debug);
        $router_debug_other = $router_debug_routing - 1;
        $router_debug =
        [
          'init'     => $router_debug_other,
          'matching' => $router_debug_other,
          'routing'  => $router_debug_routing,
          'building' => $router_debug_other,
        ];
      }
      else
      { // Preferred way to set the desired routing information.
        // Uses the same format as the $core->debug plugin, but only
        // accepts numeric value. Booleans aren't used here.
        $router_debug_def = preg_split("/[\n\,]/", $router_debug);
        $router_debug = 
        [
          'init'     => 0,
          'matching' => 0,
          'routing'  => 0,
          'building' => 0,
        ];
        foreach ($router_debug_def as $router_debug_spec)
        {
          $router_debug_spec = explode('=', trim($router_debug_spec));
          $router_debug_key = trim($router_debug_spec[0]);
          $router_debug_val = intval(trim($router_debug_spec[1]));
          $router_debug[$router_debug_key] = $router_debug_val;
        }
      }
      $this->debug = $router_debug;
    }
  }

  /**
   * Set the debugging information from the Lum Debug plugin.
   */
  public function useLumDebug ()
  {
    $core = \Lum\Core::getInstance();
    $router_debug =
    [
      'init'     => $core->debug->get('router.init',     0),
      'matching' => $core->debug->get('router.matching', 0),
      'routing'  => $core->debug->get('router.routing',  0),
      'building' => $core->debug->get('router.building', 0),
    ];
    $this->debug = $router_debug;
  }

  /**
   * Set the base_uri.
   */
  public function base_uri ($newval=Null)
  {
    if (isset($newval))
    {
      $this->base_uri = rtrim($newval, "/");
    }
    return $this->base_uri;
  }

  /**
   * Automatically set the URL prefix based on our SCRIPT_NAME.
   */
  public function auto_prefix ()
  {
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    $this->base_uri($dir);
  }

  /**
   * Add a route
   */
  public function add ($route, $is_default=False, $add_it=True, $chain=null)
  {
    if ($this->log && $this->debug['init'] > 0)
    {
      $msg = 'Router::add(';
      if ($this->debug['init'] > 1)
        $msg .= json_encode($route);
      elseif (isset($route->name))
        $msg .= json_encode($route->name);
      else
        $msg .= json_encode($route->uri);
      $msg .= ', ' . json_encode($is_default)
        . ', ' . json_encode($add_it);
      if ($this->debug['init'] > 1)
        $msg .= ', ' . json_encode($chain);
      $msg .= ')';
      error_log($msg);
    }

    if ($route instanceof Route)
    { // It's a route object.

      // Ensure proper parentage.
      $route->parent = $this;

      // Add it to our list of routes.
      if ($add_it)
        $this->routes[] = $route;

      /// Handle named routes.
      if (isset($route->name) && !isset($this->named[$route->name]))
        $this->named[$route->name] = $route;

      // Handle the default route.
      if ($is_default)
        $this->default = $route;

      if (isset($chain) && is_array($chain))
      {
        if ($this->log && $this->debug['init'] > 1)
          error_log(" :chaining => ".$route->uri);
        $this->load($chain, $route);
      }

      return $route;
    }
    elseif (is_array($route))
    { // It's options for constructing a route.
      if (isset($route['plugin']))
      {
        $plugin = $route['plugin'];
        if (isset($this->plugins[$plugin]))
        {
          $plugin = $this->plugins[$plugin];
          return $plugin($this, $route, $is_default, $add_it, $chain);
        }
      }
      $route = new Route($route);
      return $this->add($route, $is_default, $add_it, $chain); // magical recursion.
    }
    elseif (is_string($route))
    {
      if (isset($this->plugins[$route]))
      { // A plugin, let's do this!
        $plugin = $this->plugins[$route];
        return $plugin($this, $is_default, $add_it, $chain, null);
      }

      $ropts = ['uri' => $route];      
      if (is_bool($is_default))
      { // Assume the first parameter is the controller, and that the
        // URI is the same as the controller name (but with slashes.)
        $ropts['controller'] = $route;
        $ropts['name']       = $route;
        $ropts['uri']        = "/$route/";
      }
      elseif (is_array($is_default))
      { // Both controller and action specified.
        $ropts['controller'] = $ctrl   = $is_default[0];
        $ropts['action']     = $action = $is_default[1];
        $ropts['name'] = $ctrl.'_'.preg_replace('/^handle_/', '', $action);
      }
      elseif (is_string($is_default))
      { // Just a controller specified.
        $ropts['controller'] = $ropts['name'] = $is_default;
      }
      else
      { // What did you send?
        throw new RouterException("Invalid controller specified in Route::add()");
      }

      // If the third parameter is a string or array, it's allowed methods.
      if (!is_bool($add_it))
      {
        if (is_string($add_it) && in_array($add_it, ROUTE_METHODS))
        { // It's an HTTP method.
          $ropts['methods'] = [$add_it];
        }
        elseif (is_array($add_it) && in_array($add_it[0], ROUTE_METHODS))
        { // It's a list of route methods.
          $ropts['methods'] = $add_it;
        }
        $add_it = true;
      }

      // Okay, build the route, and add it.
      $route = new Route($ropts);
      return $this->add($route, false, $add_it, $chain);
    }
    else
    {
      throw new RouterException("Unrecognized route sent to Router::add()");
    }
  }

  public function addPlugin ($name, $function)
  {
    if (is_callable($function))
    {
      $this->plugins[$name] = $function;
    }
    else
    {
      throw new RouterException("Invalid plugin '$name' passed to Router.");
    }
  }

  public function initDebugging ()
  { // Compatibility with the old debug values.
    if (is_int($this->debug))
    {
      $debug_r = $this->debug;
      $debug_o = $debug_r - 1;
      $this->debug =
      [
        'init'     => $debug_o,
        'matching' => $debug_o,
        'routing'  => $debug_r,
        'building' => $debug_o,
      ];
    }
  }

  public function load (Array $routes, $parent=null)
  {
    $this->initDebugging();
    if ($this->log && $this->debug['init'] > 1)
    {
      $msg = 'load('.json_encode($routes);
      if (isset($parent))
        $msg .= ', '.$parent->uri;
      $msg .= ')';
      error_log($msg);
    }

    if (!isset($parent))
      $parent = $this;
    foreach ($routes as $route)
    {
      if (is_array($route) && isset($route[0]))
      {
        if (is_array($route[0]) && isset($route[0][0]))
        { // Nested route.
          $topdef = array_shift($route);
          $toproute = call_user_func_array([$parent,'add'], $topdef);
          $this->load($route, $toproute);
        }
        else
        { // Single def.
          $return = call_user_func_array([$parent, 'add'], $route);
          if ($return && $parent instanceof Route && $return !== $parent)
          {
            $parent = $return;
          }
        }
      }
    }
  }

  /**
   * Set a default controller. This will not be checked in the
   * normal route test, and will only be used if no other routes matched.
   */
  public function setDefault ($route)
  {
    if ($route instanceof Route)
    {
      return $this->add($route, True, False);
    }
    elseif (is_array($route))
    { // It's options for constructing a route.
      $route = new Route($route);
      return $this->add($route, True, False); // magical recursion.
    }
    elseif (is_string($route))
    { // Expects a controller with the handle_default() method.
      $route = new Route(
      [
        'controller' => $route,
        'name'       => $route,
      ]);
      return $this->add($route, True, False);
    }
  }

  /**
   * Add a redirect rule.
   */
  public function redirect ($from_uri, $to_uri, $opts=[])
  {
    $short   = isset($opts['short'])   ? $opts['short']   : False;
    $default = isset($opts['default']) ? $opts['default'] : False;
    $isroute = isset($opts['route'])   ? $opts['route']   : False;

    // Determine the appropriate target based on the 'short' option.
    $target = $short ? $to_uri : $this->base_uri . $to_uri;

    $this->add(
    [
      'uri'               => $from_uri,
      'redirect'          => $target,
      'redirect_is_route' => $isroute,
    ], $default);
  }

  /**
   * Display a view without an underlying controller.
   */
  public function display ($path, $view, $is_default=False)
  {
    $def = ['uri'=>$path];
    if (is_array($view))
    {
      $def['view_loader'] = $view[0];
      $def['view']        = $view[1];
    }
    else
    {
      $def['view'] = $view;
    }
    $this->add($def, $is_default);
  }

  /**
   * See if we can match a route against a URI and method.
   *
   * Returns a RouteContext object.
   *
   * If there is no default controller specified, and no route matches,
   * it will return Null.
   */
  public function match ($uri=Null, $method=Null)
  {
    if (is_null($uri))
    {
      $uri = $_SERVER['REQUEST_URI'];
      if (($pos = strpos($uri, '?')) !== False)
      {
        $uri = substr($uri, 0, $pos);
      }
    }
    if (is_null($method))
    { // Use the current request method.
      $method = $_SERVER['REQUEST_METHOD'];
    }
    else
    { // Force uppercase.
      $method = strtoupper($method);
    }

    $path = explode('/', $uri);

    foreach ($this->routes as $route)
    {
      $routeinfo = $route->match($uri, $method);

      if (isset($routeinfo))
      {
        $files = $request = null;
        $ct = trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]);
#        error_log("ct: $ct");
        $form1 = "application/x-www-form-urlencoded";
        $form2 = "multipart/form-data";
        if ($method == 'PUT' && ($ct == $form1 || $ct == $form2))
        { // PUT is handled different by PHP, thanks guys.
          $body = file_get_contents("php://input");
          if ($ct == $form1)
          {
            parse_str($body, $request);
          }
          else
          {
            list($request, $files) = $this->parse_multipart($body);
            if ($this->populate_put_global)
            {
              $GLOBALS['_PUT'] = $request;
            }
            if ($this->populate_put_files)
            {
              foreach ($files as $name => $spec)
              {
                if (!isset($_FILES[$name]))
                  $_FILES[$name] = $spec;
              }
            }
          }
        }
        elseif ($route->strict)
        {
          if ($method == 'GET' && isset($_GET))
          {
            $request = $_GET;
          }
          elseif ($method == 'POST' && isset($_POST))
          {
            $request = $_POST;
            $files   = $_FILES;
          }
          else
          {
            $request = $_REQUEST;
            $files   = $_FILES;
          }
        }
        else
        {
          $request = $_REQUEST;
          $files   = $_FILES;
        }

        // Let's get the body params if applicable.
        if ($this->isJSON())
        {
          $body_params = json_decode(file_get_contents("php://input"), true);
        }
        else
        {
          $body_params = [];
        }

        if ($this->isXML())
        {
          $body_text = file_get_contents("php://input");
        }
        else
        {
          $body_text = null;
        }

        $context = new RouteContext(
        [
          'router'         => $this,
          'route'          => $route,
          'path'           => $path,
          'request_params' => $request,
          'path_params'    => $routeinfo,
          'body_params'    => $body_params,
          'method'         => $method,
          'body_text'      => $body_text,
          'files'          => $files,
        ]);

        return $context;

      } // if ($routeinfo)
    } // foreach ($routes)

    // If we reached here, no matching route was found.
    // Let's send the default route.
    if (isset($this->default))
    {
      $context = new RouteContext(
      [
        'router'         => $this,
        'route'          => $this->default,
        'path'           => $path,
        'request_params' => $_REQUEST,
        'method'         => $method,
      ]);

      return $context;
    }
  } // function match()

  public function parse_multipart ($raw_body)
  {
    $data = $files = [];
    $boundary = substr($body, 0, strpos($raw_body, "\r\n"));
    if (empty($boundary))
    { // No boundary, parse as x-www-form-urlencoded instead.
      parse_str($raw_body, $data);
      return [$data, $files];
    }

    // There was a boundary, let's get the parts.
    $parts = array_splice(explode($boundary, $raw_body), 1);

    foreach($parts as $part)
    {
      if ($part == "--\r\n") break; // last part.
      $part = ltrim($part, "\r\n");
      list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

      // Parse the headers.
      $raw_headers = explode("\r\n", $raw_headers);
      $headers = [];
      foreach ($raw_headers as $header)
      {
        list($name, $value) = explode(':', $header);
        $headers[strtolower($name)] = ltrim($value, ' ');
      }
      if (isset($headers['content-disposition']))
      { // Let's parse this as either a file or a form value.
        $filename = $tmp_name = null;
        preg_match(
          '/^(.+); *name="([^"]+)"(; *filename="([^"]+")?/',
          $headers['content-disposition'],
          $matches
        );
        list(,$ftype, $name) = $matches;
        if (isset($matches[4]))
        { // It's a file.
          if (isset($files[$name])) continue; // skip duplicates.
          $filename = $matches[4];
          $filename_parts = pathinfo($filename);
          $output = $filename_parts['filename'];
          $tmp_name = tempnam(ini_get('upload_tmp_dir'), $outname);

          if (isset($headers['content-type']))
            $type = strtolower($headers['content-type']);
          else
            $type = $ftype;

          $files[$name] =
          [
            'error'    => 0,
            'name'     => $filename,
            'tmp_name' => $tmp_name,
            'size'     => strlen($body),
            'type'     => $type,
          ];

          file_put_contents($tmp_name, $body);
        }
        else
        { // It's not a file, add it to the data.
          $data[$name] = substr($body, 0, strlen($body) - 2);
        }
      }
    }
    return [$data, $files];
  }

  public function isJSON ()
  {
    return $this->isContentType(JSON_TYPE, false);
  }

  public function isXML ()
  {
    return $this->isContentType(XML_TYPE, false);
  }

  public function isContentType ($wanttype, $forcelc=true)
  {
    if ($forcelc)
      $wanttype = strtolower($wanttype);
    $havetype = $this->contentType(false);
    return ($wanttype == $havetype);
  }

  public function contentType ($withOpts=false)
  {
    $ctypedef = explode(';', $_SERVER['CONTENT_TYPE']);
    $ctype = strtolower(array_shift($ctypedef));
    if ($withOpts)
    {
      $opts = [];
      foreach ($ctypedef as $optstr)
      {
        $optdef  = explode('=', $optstr, 2);
        // the option name should be in lowercase.
        $optname = strtolower($optdef[0]);
        // strip whitespace and " characters from the values.
        $optval  = trim($opdef[1], " \t\n\r\0\x0B\"");
        $opts[$optname] = $optval;
      }
      return [$ctype, $opts];
    }
    return $ctype;
  }

  /**
   * Return the accept header itself.
   */
  public function accept ()
  {
    return strtolower($_SERVER['HTTP_ACCEPT']);
  }

  /**
   * Get a list of accept headers.
   */
  public function accepts ($mimeTypes=null)
  {
    // No header, return null.
    if (!isset($_SERVER['HTTP_ACCEPT'])) return null;

    $acceptTypes = [];
    $acceptRaw = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
    $acceptRaw = explode(',', $acceptRaw);

    foreach ($acceptRaw as $a)
    {
      $q = 1;
      if (strpos($a, ';q='))
      {
        list($a, $q) = explode(';q=', $a);
      }
      $acceptTypes[$a] = $q;
    }
    arsort($acceptTypes);
    
    // No desired mime type(s), return the full list.
    if (!$mimeTypes) return $acceptTypes;

    if (is_string($mimeTypes))
    { // Search for a single mime type.
      $mimeTypes = strtolower($mimeTypes);
      foreach ($acceptTypes as $mime => $q)
      {
        if ($q && $mimeTypes == $mime) return true; // was found, return true.
      }
      // String wasn't found, return false.
      return false;
    }

    // Search for one of several mime types.    
    $mimeTypes = array_map('strtolower', (array)$mimeTypes);
    
    foreach  ($acceptTypes as $mime => $q)
    {
      if ($q && in_array($mime, $mimeTypes)) return $mime;
    }

    // Nothing matched.
    return null;
  }
  
  /**
   * We accept JSON
   */
  public function acceptsJSON ()
  {
    return $this->accepts(JSON_TYPE);
  }

  /**
   * We accept XML
   * Note: several browsers send application/xml in the default
   * Accept header, so this may not be the best way to determine
   * if XML is the desired format. Reality sucks.
   */
  public function acceptsXML ()
  {
    return $this->accepts(XML_TYPE);
  }

  /**
   * The primary frontend function for starting the routing.
   */
  public function route ($uri=Null, $method=Null)
  {
    $core = \Lum\Core::getInstance();
    $context = $this->match($uri, $method);
    if (isset($context))
    { // We found a match.
      $this->current = $context;

      $route = $context->route;
      if ($this->log && $route->name)
        error_log("Dispatching to {$route->name}");

      if ($route->redirect)
      { // Whether we redirect to a URL, or go to a known route,
        // depends on the redirect_is_route setting.
        if ($this->log && $this->debug['routing'] > 0)
          error_log(" :redirect => ".$route->redirect);
        if ($route->redirect_is_route)
        {
          $this->go($route->redirect, $context->path_params);
        }
        else
        {
          $core->url->redirect($route->redirect);
        }
      }
      elseif ($route->view)
      { // We're loading a view.
        if ($this->log && $this->debug['routing'] > 0)
          error_log(" :view => ".$route->view);
        if (isset($route->view_status))
        {
          http_response_code($route->view_status);
        }
        $loader = $route->view_loader;
        return $core->$loader->load($route->view, $context->to_array());
      }
      elseif ($route->controller)
      {
        if ($this->log && $this->debug['routing'] > 0)
          error_log(" :controller => ".$route->controller);
        // We consider it a fatal error if the controller doesn't exist.
        $controller = $core->controllers->load($route->controller);

        if (is_callable([$controller, 'init_route']))
        {
          $controller->init_route($context);
        }

        $action = $route->action;
        if (is_callable([$controller, $action]))
        {
          if ($this->log && $this->debug['routing'] > 0)
            error_log(" :action => $action");
          return $controller->$action($context);
        }
        else
        {
          throw new RouterException("Controller action $action not found.");
        }
      }
      else
      {
        throw new RouterException("Invalid Route definition.");
      }
    }
    else
    {
      throw new RouterException("No route matched, and no default controller set.");
    }
  }

  /**
   * Build a URI for a named route.
   */
  public function build ($routeName, $params=[], $opts=[])
  {
    if ($this->log && $this->debug['building'] > 1)
    {
      $call = "Router::build($routeName";
      if ($this->debug > 2) 
        $call .= ", " .
          json_encode($params) . ", " .
          json_encode($opts);
      $call .= ")";
      error_log($call);
    }

    if (!isset($this->named[$routeName]))
      throw new 
        RouterException("No named route '$routeName' in call to Router::build()");

    unset($opts['fulluri']); // Keep our sanity.
    $route_uri = $this->named[$routeName]->build($params, $opts);
    if (isset($opts['short']) && $opts['short'])
      return $route_uri;
    else
      return $this->base_uri . $route_uri;
  }

  /**
   * Redirect the browser to a known route, with the appropriate parameters.
   */
  public function go ($routeName, $params=[], $ropts=[], $bopts=[])
  {
    if ($this->log && $this->debug['routing'] > 1)
    {
      $call = "Router::go($routeName";
      if ($this->debug > 2)
      {
        $call .= ', '
        . json_encode($params) . ', '
        . json_encode($ropts)  . ', '
        . json_encode($bopts);
      }
      $call .= ')';
      error_log($call);
    }
    $uri  = $this->build($routeName, $params, $bopts);
    $core = \Lum\Core::getInstance();
    $core->url->redirect($uri, $ropts);
  }

  /**
   * Check to see if we know about a named route.
   */
  public function has ($routeName)
  {
    return isset($this->named[$routeName]);
  }

  /**
   * Get a named route.
   */
  public function get_route ($routeName)
  {
    if (isset($this->named[$routeName]))
      return $this->named[$routeName];
  }

}

/**
 * A shared trait offering a really simple constructor.
 */
Trait RouteConstructor
{
  public function __construct ($opts=[])
  {
    foreach (get_object_vars($this) as $field => $default)
    {
      if (isset($opts[$field]))
        $this->$field = $opts[$field];
    }
  }
}

/**
 * An individual Route.
 */
class Route
{
  use RouteConstructor;

  public $parent;                                    // The Router object.
  public $name;                                      // Optional name.
  public $uri         = '';                          // URI to match.
  public $controller;                                // Target controller.
  public $action      = 'handle_default';            // Target action.
  public $strict      = False;                       // Request data source.
  public $redirect;                                  // If set, we redirect.
  public $view_loader = 'views';                     // Used with 'view'.
  public $view;                                      // A view to load.
  public $view_status;                               // HTTP status override.
  public $content_type;                              // Only certain CT.
  public $is_json     = false;                       // Only JSON content.
  public $is_xml      = false;                       // Only XML content.
  public $accepts;                                   // Only certain Accept.
  public $want_json   = false;                       // Only Accept JSON.
  public $want_xml    = false;                       // Only accept XML.

  public $methods = ['GET','POST'];                  // Supported methods.

  public $redirect_is_route = False;                 // Redirect to a route?

  protected $filters = [];                           // Parameter filters.

  protected function uri_regex ()
  {
    return preg_replace_callback
    (
      "/:([\w-]+)/", 
      [$this, 'substitute_filter'], 
      $this->uri
    );
  }

  protected function substitute_filter ($matches)
  {
    if (isset($matches[1]) && isset($this->filters[$matches[1]]))
    {
      return $this->filters[$matches[1]];
    }
    return $this->parent->default_filter; // The default filter.
  }

  public function match ($uri, $method)
  {
    $debug = $this->parent->log ? $this->parent->debug['matching'] : 0;
    if ($debug > 0)
    {
      error_log("Route[{$this->uri}]::match($uri, $method)");
    }

    if (! in_array($method, $this->methods)) return; // Doesn't match method.

    if ($debug > 1)
      error_log(" -- HTTP method matched.");

    if ($this->is_json && !$this->parent->isJSON()) return; // Not JSON.
    if ($this->is_xml  && !$this->parent->isXML()) return;  // Not XML.
    if ($this->want_json && !$this->parent->acceptsJSON()) return;
    if ($this->want_xml && !$this->parent->acceptsXML()) return;

    if ($debug > 1)
      error_log(" -- is/want tests matched.");

    // If a specific content_type has been specified, make sure it matches.
    if (isset($this->content_type) 
      && !$this->parent->isContentType($this->content_type)) return;

    if ($debug > 1)
      error_log(" -- Content Type matched.");

    // If a specific Accept type has been specified, make sure it matches.
    if (isset($this->accepts)
      && !$this->parent->accepts($this->accepts)) return;

    if ($debug > 1)
      error_log(" -- Accepts matched.");

    if ($this->parent->base_uri || $this->uri_regex())
    {
      $match = "@^"
             . $this->parent->base_uri
             . $this->uri_regex()
             . "*$@i";

      if ($debug > 2)
        error_log(" :regex => $match");

      if (! preg_match($match, $uri, $matches)) return; // Doesn't match URI.
    }

    if ($debug > 0)
      error_log(" -- Route matched.");

    $params = [];

    if (preg_match_all("/:([\w-]+)/", $this->uri, $argument_keys))
    {
      $argument_keys = $argument_keys[1];
      foreach ($argument_keys as $key => $name)
      {
        if (isset($matches[$key + 1]))
        {
          $params[$name] = $matches[$key + 1];
        }
      }
    }

    if ($debug > 2)
      error_log(" :params => ".json_encode($params));

    return $params;

  }

  public function build ($params=[], $opts=[])
  {
    $uri = $this->uri;

    // First, we replace any sent parameters.
    if ($params && preg_match_all("/:([\w-]+)/", $uri, $param_keys))
    {
      $param_keys = $param_keys[1];
      foreach ($param_keys as $key)
      {
        if (isset($params[$key]))
        {
          $uri = preg_replace("/:([\w-]+)/", $params[$key], $uri, 1);
        }
      }
    }

    // Okay, a sanity check. If there are still placeholders, we have
    // a problem, and cannot continue.
    // Pass ['strict'=>False] to make this non-fatal.
    $strict = isset($opts['strict']) ? $opts['strict'] : True;
    if (preg_match_all("/:([\w-]+)/", $uri, $not_found))
    {
      $not_found = $not_found[1];
      $not_found = join(', ', $not_found);
      if ($strict)
      {
        throw new RouterException("Route::build() is missing: $not_found");
      }
      else
      {
        return Null;
      }
    }

    if (isset($opts['fulluri']) && $opts['fulluri'])
      $uri = $this->parent->base_uri . $uri;

    return $uri;
  }

  // For chaining Routes.
  public function add ($suburi, $action=Null, $rechain=False, $nestchain=null)
  {
    $ctrl = $this->controller;
    $baseuri = rtrim($this->uri, "/");
    if (is_array($action))
    {
      $ropts = $action;
      $ropts['uri'] = $baseuri . $suburi;
      if (!isset($ropts['controller']))
        $ropts['controller'] = $ctrl;
    }
    elseif (is_string($action))
    { // Specified the action, using our controller and path.
      $ropts =
      [
        'uri'        => $baseuri . $suburi,
        'action'     => $action,
        'name'       => $ctrl . '_' . preg_replace('/^handle_/', '', $action),
        'controller' => $ctrl,
      ];
    }
    else
    { // Action will be 'handle_suburi', don't include the / in the $suburi.
      $ropts =
      [
        'uri'        => "$baseuri/$suburi/",
        'action'     => 'handle_' . $suburi,
        'name'       => $ctrl . '_' . $suburi,
        'controller' => $ctrl,
      ];
    }

    // If the third parameter is a string or array, it's allowed methods.
    if (!is_bool($rechain))
    {
      if (is_string($rechain) && in_array($rechain, ROUTE_METHODS))
      {
        $ropts['methods'] = [$rechain];
      }
      elseif (is_array($rechain) && in_array($rechain[0], ROUTE_METHODS))
      {
        $ropts['methods'] = $rechain;
      }
      // Reset rechain back to a boolean value.
      $rechain = False;
    }

    // Build the sub-route with our compiled options.
    $subroute = new Route($ropts);
    $this->parent->add($subroute, false, true, $nestchain);

    if ($rechain)
      return $subroute;
    else
      return $this;
  }

}

/**
 * A routing context object. Sent to controllers.
 */
class RouteContext implements \ArrayAccess
{
  use RouteConstructor;

  public $router;              // The router object.
  public $route;               // The route object.
  public $path           = []; // The URI path elements.
  public $request_params = []; // The $_REQUEST, $_GET or $_POST data.
  public $path_params    = []; // Parameters specified in the URI.
  public $body_params    = []; // Params found in a JSON body, if applicable.
  public $body_text;           // Body text (currently XML is supported.)
  public $method;              // The HTTP method used.
  public $files;               // Any files uploaded.
  public $offset_files = true; // Include files in offset* methods.

  // Convert this into a simple array structure.
  public function to_array ($opts=[])
  {
    $array =  $this->path_params + $this->body_params + $this->request_params;
    if (isset($opts['files']) && $opts['files'] && isset($this->files))
      $array = $array + $this->files;
    $array['_context'] = $this;
    return $array;
  }

  public function offsetGet ($offset)
  {
    if (array_key_exists($offset, $this->path_params))
    {
      return $this->path_params[$offset];
    }
    elseif (array_key_exists($offset, $this->body_params))
    {
      return $this->body_params[$offset];
    }
    elseif (array_key_exists($offset, $this->request_params))
    {
      return $this->request_params[$offset];
    }
    elseif ($this->offset_files && isset($this->files, $this->files[$offset]))
    {
      return $this->getFile($offset);
    }
    else
    {
      return Null;
    }
  }

  public function offsetSet ($offset, $value)
  {
    throw new RouterException ("Context parameters are read only.");
  }

  public function offsetExists ($offset)
  {
    if (array_key_exists($offset, $this->path_params))
    {
      return True;
    }
    elseif (array_key_exists($offset, $this->body_params))
    {
      return True;
    }
    elseif (array_key_exists($offset, $this->request_params))
    {
      return True;
    }
    elseif ($this->offset_files && \Lum\File::hasUpload($offset, $this))
    {
      return True;
    }
    else
    {
      return False;
    }
  }

  public function offsetUnset ($offset)
  {
    throw new RouterException ("Cannot unset a context parameter.");
  }

  public function getFile ($name)
  {
    return \Lum\File::getUpload($name, $this);
  }

  public function isJSON ()
  {
    return $this->router->isJSON();
  }

  public function isXML ()
  {
    return $this->router->isXML();
  }

  public function acceptsJSON ()
  {
    return $this->router->acceptsJSON();
  }

  public function acceptsXML ()
  {
    return $this->router->acceptsXML();
  }

  public function accepts ($mimetype=null)
  {
    return $this->router->accepts($mimetype);
  }

  public function isContentType ($contentType)
  {
    return $this->router->isContentType($contentType);
  }

  public function contentType ($withOpts=false)
  {
    return $this->router->contentType($withOpts);
  }

  public function jsonBody ()
  {
    if ($this->router->isJSON())
    {
      return $this->body_params;
    }
  }

  public function xmlBody ()
  {
    if ($this->router->isXML())
    {
      return $this->body_text;
    }
  }

}


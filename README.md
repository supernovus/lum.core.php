# lum.core.php

## NOTE

This is currently in the process of being ripped apart.
I will not release it as version `3.0` until I'm finished everything in the
[TODO](TODO.md) document.

## Summary

The `Lum\Core` library, and it's primary plugins and helpers.

## Classes

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\Core                | The Core class itself.                            |
| Lum\Plugins\Capture     | A helper for capturing PHP output.                |
| Lum\Plugins\Client      | A plugin to get information on client.            |
| Lum\Plugins\Controllers | A plugin for loading controllers.                 |
| Lum\Plugins\Fakeserver  | A plugin for using 'cli' or 'cli-server' SAPI.    |
| Lum\Plugins\Instance    | A base class for loading class instances.         |
| Lum\Plugins\Models      | A plugin for loading models.                      |
| Lum\Plugins\Output      | A plugin for setting output options.              |
| Lum\Plugins\Plugins     | A meta-plugin for loading plugins.                |
| Lum\Plugins\Sess        | A plugin for managing PHP Sessions.               |
| Lum\Plugins\Site        | A plugin for building simple PHP sites.           |
| Lum\Plugins\Url         | A plugin for working with URLs.                   |
| Lum\Plugins\Views       | A plugin for loading views.                       |
| Lum\Loader\Content      | A loader trait for parsing PHP content.           |
| Lum\Loader\Files        | A loader trait for finding files in a folder.     |
| Lum\Loader\Instance     | A loader trait for creating an object instance.   |
| Lum\Loader\Namespaces   | A loader trait for finding classes in namespaces. |

## Creating a Core instance

You only need to do this **once** in your application, generally right at the
beginning (either at the very top of the main PHP script, or right after
a `namespace` declaration if the script itself is inside a *PHP Namespace*.)

```php
require_once 'vendor/autoload.php';  // Register Composer autoloaders.
\Lum\Autoload::register();           // If using spl_autoload, call this.
$core = \Lum\Core::getInstance();    // Create your Core object.
```

The first line is a standard for anything using *Composer*, and the
second line is a compatibility function from `lum-compat` that allows
the use of classic *SPL* autoloading along side *Composer* autoloading.

The last line is the one that actually creates the *singleton instance*.

You **cannot** use the `new \Lum\Core()` style constructor with this class.
The constructor is *protected* and can only be called via `getInstance()`.

## Getting the current Core instance

Any time you need the Core, you simply call the `getInstance()` method.

```php
$core = \Lum\Core::getInstance();  // Return the current core instance.
```

Since this is using a *singleton instance*, it will always return the same
object no matter where or how many times it is called.

## Using plugins

The plugins are loaded automatically when first called. For instance:

```php
$lum = \Lum\Core::getInstance(); 
$lum->conf->setDir('./conf'); // Load Conf plugin and call setDir() on it.   
$lum->router = ['extend'=>true, 'auto_prefix'=>true]; // Load Router plugin.
$lum->router->loadRoutes($lum->conf->routes); // Load routes into Router.
$lum->controllers->addNS("\\MyApp\\Controllers"); // Load Controllers plugin.
$lum->models->addNS("\\MyApp\\Models"); // Load Models plugin.
$lum->controllers->use_screens(); // Use default view loaders.
$lum->dispatch(); // Call an extension method added by Router plugin.
```

The above example is written assuming `lum-core-plugins` is also installed,
as the `conf` and `router` plugins have been split into their own packages
and are no longer included by default, but `lum-core-plugins` includes all
of the split-off plugins.

## Official URLs

This library can be found in two places:

 * [Github](https://github.com/supernovus/lum.core.php)
 * [Packageist](https://packagist.org/packages/lum/lum-core)

## Author

Timothy Totten

## License

[MIT](https://spdx.org/licenses/MIT.html)

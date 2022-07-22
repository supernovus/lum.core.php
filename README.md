# lum.core.php

## Summary

A small class with a singleton instance that can be used to manage the
lifecycle of a PHP application, acting as a director for models, controllers,
and views.

It also supports additional plugins that have a ton of extra functionality.

Despite the name of the package and class, this is not in fact the singular 
*core* library that everything else in the **Lum.php** collections builds upon.
It used to be in the `1.x` days, but has since become just the App Director
singleton class. See [lum-compat](https://github.com/supernovus/lum.compat.php)
for the *true* fundamental core class that the whole PHP library set uses.

## Classes

| Class                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\Core                | The Core class itself.                            |
| Lum\Plugins\Capture     | A helper for capturing PHP output.                |
| Lum\Plugins\Controllers | A plugin for loading controllers.                 |
| Lum\Plugins\Instance    | A base class for loading class instances.         |
| Lum\Plugins\Models      | A plugin for loading models.                      |
| Lum\Plugins\Plugins     | A meta-plugin for loading plugins.                |
| Lum\Plugins\Sess        | A plugin for managing PHP Sessions.               |
| Lum\Plugins\Views       | A plugin for loading views.                       |

## Traits

| Trait                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| Lum\Loader\Content      | A loader trait for parsing PHP content.           |
| Lum\Loader\Files        | A loader trait for finding files in a folder.     |
| Lum\Loader\Instance     | A loader trait for creating an object instance.   |
| Lum\Loader\Namespaces   | A loader trait for finding classes in namespaces. |

## Note on Plugins

Prior to `v3.0` most of the additional plugins were included in the `core`
package itself, but I decided that it made more sense to split them off into
their own packages. A *suggested* 
[lum-core-plugins](https://github.com/supernovus/lum.core-plugins.php) 
*meta-package* will be available to install all of the plugins that used to 
be included in this package.

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

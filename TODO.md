# TODO

## Splitting the Core

I'm currently in the process of pulling stuff out of the core and
into their own libraries. While I've pulled all the libraries that are
being moved, I have not finished with the replacement
libraries yet. These are the items remaining:

- `Plugins\{Client,Output,Url,Site}` => `lum-web`; `{file,encode}`.
   - Will make new standalone classes in `Lum\Web` namespace.
   - Will add `lum-plugins-web` compatibility plugins as well.
- `Plugins\Router,Router\*` => `lum-router`; `{meta,web}`.
   - Will make new split classes in `Lum\Router` namespace.
   - Will add a `lum-plugins-router` plugin with full backwards compatibility.
- `Plugins\Fakeserver` => `lum-cli`; no deps.
   - Will also move `Lum\CLI` from `lum-framework` into this package.
   - Again, a `lum-plugins-fakeserver` will exist for compatibility.

This will leave a much smaller `lum-core` package with only a `lum-compat`
dependency, and a lot fewer default plugins.

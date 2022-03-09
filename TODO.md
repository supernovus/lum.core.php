# TODO

## Splitting the Core

I think that the `lum-core` package, while thinner than before, is
still rather bloated. I'm planning on splitting off a few more items.

- `Data\*` => `lum-data`; no deps.
- `Plugins\{Client,Output,Url,Site}` => `lum-web`; {file,encode}.
   - Will make new standalone classes in `Lum\Web` namespace.
   - Will add compatibility plugins for the original three classes.
- `Plugins\Router,Router\*` => `lum-router`; {web}.
   - Will make new split classes in `Lum\Router` namespace.
   - Will add a `Lum\Plugins\Router` plugin with full backwards compatibility.
   - See the plugin source for further details.
- `Plugins\Conf` => `lum-conf`; {data,arrays}.
- `Plugins\Fakeserver` => `lum-cli`; {}.
   - Will also move `Lum\CLI` from `lum-framework` into this package.

This will leave a much smaller `lum-core` package with only a `lum-compat`
dependency, and fewer default plugins.

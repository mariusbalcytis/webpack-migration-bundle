Bundle to Help Migrating From Assetic to Webpack
====

Bundle to help integrating from [assetic](https://github.com/kriswallsmith/assetic)
to [webpack](https://webpack.github.io/).

It uses [maba/webpack-bundle](https://github.com/mariusbalcytis/webpack-bundle) and
[symfony/assetic-bundle](https://github.com/symfony/assetic-bundle) as dependencies.

What is webpack and why to migrate from assetic?
----

Webpack is module bundler and CommonJS / AMD dependency manager.

For me, it replaces both grunt/gulp and RequireJS.

See [what is webpack?](http://webpack.github.io/docs/what-is-webpack.html)
and [it's documentation](http://webpack.github.io/docs/) for more information.

For comparison with assetic and alternative webpack-based solutions, see
[maba/webpack-bundle](https://github.com/mariusbalcytis/webpack-bundle).

What does this bundle do?
----

1. Registers twig node visitor.
2. This visitor replaces assetic nodes (`stylesheets` and `javascripts` nodes) with `webpack_asset` function.

Well, basically, that's all it does. `webpack_asset` function is handled in `maba/webpack-bundle` and this
bundle has nothing to do with it.

Worth to note that it **does** support assetic variables.

Also worth to note that it basically **ignores** assetic filters, configured via extension in `config.yml`. Please configure and use webpack loaders
and plugins as an alternative. Furthermore, you should disable them as they **are** called for named assets thus giving performance impact.

Installation
----

```shell
composer require maba/webpack-migration-bundle
```

Inside `AppKernel`:

```php
new Maba\Bundle\WebpackBundle\MabaWebpackBundle(),  // if you don't have it already
new Maba\Bundle\WebpackBundle\MabaWebpackMigrationBundle(),
```

Setup files for webpack bundle (see [maba/webpack-bundle](https://github.com/mariusbalcytis/webpack-bundle) for more information what this does):

```shell
app/console maba:webpack:setup
```

Configure webpack to extract CSS into separate files. This is needed for `stylesheets` tags to work.

```yml
maba_webpack:
    config:
        parameters:
            extract_css: true
```

Install npm dependency used by WebpackMigrationBundle which is not installed by default in WebpackBundle:

```shell
npm install imports-loader --save-dev
```

Imports loader is used to change some global variables when loading Assetic javascripts for as much out-of-the-box
compatibility as possible. In webpack context, `this` is not `window` like when adding common `<script>` tag,
`this` points to `module.exports`. This is handled by default by most libraries and when running in such mode,
no variables are registered in global context (`window`), they are just exported in CommonJS way.

This breaks things, as your current code expects to find `jQuery`, `angular` etc. in global context.

This loader fixes this in most of the cases. This might not always work out-of-the-box, see "Gotchas" bellow.

Usage
----

Just use assetic nodes as usual. Only `stylesheets` and `javascript` nodes are replaced.

Migrating from this migration bundle
----

Let's say you have such twig template:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {% stylesheets '@ApplicationBundle/Resources/assets/featureA.css'
                   '@ApplicationBundle/Resources/assets/featureB.css' %}
        <link rel="stylesheet" href="{{ asset_url }}"/>
    {% endstylesheets %}
</head>
<body>
    {% javascripts '@ApplicationBundle/Resources/assets/featureA.js'
                   '@ApplicationBundle/Resources/assets/featureB.js' %}
        <script src="{{ asset_url }}"></script>
    {% endjavascripts %}
</body>
</html>
```

Move css requirements to javascript files:

```js
// @ApplicationBundle/Resources/assets/featureA.js
require('featureA.css');

// ... rest of the code
```

```js
// @ApplicationBundle/Resources/assets/featureB.js
require('featureB.css');

// ... rest of the code
```

Make entry point javascript file:

```js
// @ApplicationBundle/Resources/assets/main.js

require('featureA.js');
require('featureB.js');
```

Change your twig template:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <script src="{{ webpack_asset('@ApplicationBundle/Resources/assets/main.js') }}"></script>
</body>
</html>
```

If you want to load CSS initially, enable CSS extraction (this enables `ExtractTextPlugin`, see `webpack.config.js`):

```yml
maba_webpack:
    config:
        parameters:
            extract_css: true
```

Provide URL to stylesheet:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="{{ webpack_asset('@ApplicationBundle/Resources/assets/main.js', 'css') }}"/>
</head>
<body>
    <script src="{{ webpack_asset('@ApplicationBundle/Resources/assets/main.js') }}"></script>
</body>
</html>
```

Keep in mind: first parameter to `webpack_asset` is always a javascript file, which requires
(recursivelly) css files, second parameter is asset type - `css`.

Also, if you enable `ExtractTextPlugin`, you must always include initial stylesheet.
If styles are loaded asynchronously (for example, inside `require.ensure([], function() {...});`),
they are always applied automatically.

Gotchas
----

If you use named assets (for example `assetic.assets.jquery` in `config.yml` with `@jquery` in twig template),
Assetic gives `AssetReference`, which does not provide source file(s). In this case, bundle dumps the contents
of the asset inside cache directory and references that file to webpack.

This means that if you change any inputs from named assets, you have to clear the cache for changes to take effect.
If you use named assets mainly for vendor libraries, this is not such an issue as you don't normally change vendor
files while developing.

Bundle does the same (dumps generated asset) if there are any filters set inside `stylesheets` or `javascripts` tag.
This is to support any custom filters that cannot be provided by webpack.

Any filters set by file extension are stripped down in normal cases (where source file can be referenced). So keep sure
that you configure webpack loaders and/or plug-ins that do the same as your assetic filters.

As such filters like `uglify` or `less` are duplicated in both assetic and webpack, remove them from assetic to
potentially gain some performance boost on `cache:clear` command.

In webpack context, `this` is not `window`, but `module.exports`. For as much compatibility as possible,
imports-loader is used with this configuration: `imports?this=>window!imports?define=>false!imports?module=>false`.
This handles most of the cases, but it might fail if `var variableName` is used instead of `window.variableName`.

If your CSS files gives links to assets, they are tried to be included as data-uri. If links are absolute, they
are not included but left as is. This could be an issue in development environment, where CSS files are loaded
from webpack-dev-server (localhost:8080 by default) - it does not serve other assets, just the ones webpack generated.
So in these cases you could get 404s (just in development environment).

Again - use this for migration or testing webpack, not for long-running development workflows.

## Running tests

[![Travis status](https://travis-ci.org/mariusbalcytis/webpack-migration-bundle.svg?branch=master)](https://travis-ci.org/mariusbalcytis/webpack-migration-bundle)

```shell
composer install
vendor/bin/codecept run
```

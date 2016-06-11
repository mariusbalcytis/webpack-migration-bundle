Bundle to Help Migrating From Assetic to Webpack
====

Bundle to help integrating from [assetic](https://github.com/kriswallsmith/assetic)
to [webpack](https://webpack.github.io/).

It uses [maba/webpack-bundle](https://github.com/mariusbalcytis/webpack-bundle) and
[symfony/assetic-bundle](https://github.com/symfony/assetic-bundle) as dependencies.

It creates and **modifies** files in your repository. This means that it is not meant to be run in production - 
install it, use it and remove it from your project. Always keep sure to use version system like git and have no
uncommitted changes as you might loose your stuff.

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

1. Finds assetic nodes (`stylesheets` and `javascripts` nodes) in your twig templates.
2. Dumps `js` files representing bundled assets.
3. Replaces them with `webpack_asset` function with reference to dumped file.
4. Dumps configured named assets inside `config.yml`.

[twig-template-modification-bundle](https://github.com/mariusbalcytis/twig-template-modification-bundle)
is used for replacing the twig templates themselves.

Worth to note that it **does** support assetic variables, but as the result is usable,
it's not really manageable in the long scale. Please see other means to accomplish this with conditional loading
from the javascript itself. See
[symfony-webpack-angular-demo](https://github.com/mariusbalcytis/symfony-webpack-angular-demo) for an example
how this could be done with locales.

Also worth to note that it **ignores** `images` assetic nodes and other nodes with unrecognised filters.
You can configure ignored filters in `config.yml` by providing `maba_webpack_migration.ignored_filters` parameter.

By default, these filters are ignored:
- cssrewrite
- less
- lessphp
- scssphp
- sassphp
- jsqueeze
- uglifyjs
- uglifyjs2
- uglifycss
- yui_css
- yui_js

They are ignored, as `js` and `css` files are minified by default on production,
and SCSS and Less files work out of the box.
Assumption is made that you use correct extension for your file types (`.less` for Less files etc.)

Installation and Usage
----

```shell
composer require maba/webpack-migration-bundle
```

Inside `AppKernel`:

```php
new Maba\Bundle\WebpackBundle\MabaWebpackBundle(),  // if you don't have it already
new Maba\Bundle\WebpackBundle\MabaTwigTemplateModificationBundle(), // dependency
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

Install npm dependencies used by WebpackMigrationBundle which are not installed by default in WebpackBundle:

```shell
npm install imports-loader exports-loader expose-loader --save-dev
```

In webpack context, `this` is not `window` like when adding common `<script>` tag,
`this` points to `module.exports`. This is handled by default by most libraries and when running in such mode,
no variables are registered in global context (`window`), they are just exported in CommonJS way.

This breaks things, as your current code expects to find `jQuery`, `angular` etc. in global context.

This bundle analyses JavaScript file for common patterns and tries to use correct loaders to fix these issues.
This might not always work out-of-the-box. You can always use additional loader or modify
the generated code - bundle only creates and replaces files in your repository,
all other modifications to the code after that can be made manually.

Run replacement command:

```shell
app/console maba:webpack-migration:modify-twig-templates
```

Now your twig files are modified - you can safely remove both bundles (this one and MabaTwigTemplateModificationBundle)
from your kernel and vendors. You can just revert your changes in `AppKernel.php`, `composer.json` and `composer.lock`,
asserting that you've installed MabaWebpackBundle separately.

## Running tests

[![Travis status](https://travis-ci.org/mariusbalcytis/webpack-migration-bundle.svg?branch=master)](https://travis-ci.org/mariusbalcytis/webpack-migration-bundle)

```shell
composer install
vendor/bin/phpunit
```

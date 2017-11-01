EbuildyBump
======================

Integration of the [**bump**](https://github.com/subitolabs/bump-bundle) library
into Symfony.

Installation
------------

Require [`subitolabs/bump-bundle`](https://packagist.org/packages/subitolabs/bump-bundle)
to your `composer.json` file:


```json
{
    "require": {
        "subitolabs/bump-bundle": "~1.0"
    }
}
```

Register the bundle in `app/AppKernel.php`:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles[] = new Subitolabs\Bundle\BumpBundle\SubitolabsBumpBundle();
}
```

Console Command
---------------

### bump

```
Usage:
  subitolabs:bump [options] [--] <env> [<position>]

Arguments:
  env                          Environment
  position                     Position to increment: 0=nothing(default), 1=MAJOR, 2=MINOR, 3=PATCH [default: 0]

Options:
      --dry-run                Set to not alter data and git something
      --message[=MESSAGE]      Tag message [default: "Bump to {{tag}} with Subitolabs bump bundle"]
      --tag[=TAG]              How tag is made [default: "{{env}}-{{version}}"]
      --file[=FILE]            File to write version info (JSON encoded) [default: "./app/config/version.yml"]
      --changelog[=CHANGELOG]  CHANGELOG.md path [default: "./CHANGELOG.md"]
  -h, --help                   Display this help message
  -q, --quiet                  Do not output any message
  -V, --version                Display this application version
      --ansi                   Force ANSI output
      --no-ansi                Disable ANSI output
  -n, --no-interaction         Do not ask any interactive question
  -e, --env=ENV                The environment name [default: "dev"]
      --no-debug               Switches off debug mode
  -v|vv|vvv, --verbose         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Bump version according semantic versioning (http://semver.org/) - create git tag.
```

### changelog

```
Usage:
  subitolabs:changelog [options]

Options:
      --changelog[=CHANGELOG]  CHANGELOG.md path [default: "./CHANGELOG.md"]
  -h, --help                   Display this help message
  -q, --quiet                  Do not output any message
  -V, --version                Display this application version
      --ansi                   Force ANSI output
      --no-ansi                Disable ANSI output
  -n, --no-interaction         Do not ask any interactive question
  -e, --env=ENV                The environment name [default: "dev"]
      --no-debug               Switches off debug mode
  -v|vv|vvv, --verbose         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Write full changelog based on git tags and git logs.
```

Configuration
-------------

Nothing to configure.
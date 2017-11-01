EbuildyBump
======================

Integration of the [**bump**](http://github.com/ebuildy/bump) library
into Symfony.

Installation
------------

Require [`ebuildy/bump`](https://packagist.org/packages/ebuildy/bump)
to your `composer.json` file:


```json
{
    "require": {
        "ebuildy/bump": "~1.0"
    }
}
```

Register the bundle in `app/AppKernel.php`:

```php
// app/AppKernel.php
public function registerBundles()
{
    return array(
        // ...
        new Ebuildy\BumpBundle\EbuildyBumpBundle()
    );
}
```

Console Command
---------------

A Symfony console command is provided in this bundle

```
$ bin/console ebuildy:bump

Options available:

 --consumer             Which consumer should we use ? (default: "default")
 --iteration-limit (-i) Limit of iterations, -1 for infinite.. (default: -1)
 --time-limit           During how many time this command will listen to the queue. (default: 60)
 --usleep               Micro seconds (default: 100000)
 --lock                 Only one command processing ?
```

Configuration
-------------

Nothing to configure.
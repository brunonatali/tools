# Tools

Set of tools that will help you to develop a program without need to rewrite code.

This lib was write thinking to help new projects, bringing them to life much more easier.

**Table of Contents**

* [Quickstart example](#quickstart-example)
* [System Interaction](#connection-usage)
  * [setAppInitiated()](#setAppInitiated)
	* [getAppInitiated()](#getAppInitiated)
	* [setFunctionOnAppAborted()](#setFunctionOnAppAborted)
  * [setAborted()](#setAborted)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## Quickstart example

This is the ways to be construct this:

```php
$systemInteraction = new BrunoNatali\Tools\SystemInteraction();

alternatively you can set system folder by hand

$mySystemFolder = ['/home/user/myApp/', '/var/myfolder/myApp/'];
$systemInteraction = new BrunoNatali\Tools\SystemInteraction($mySystemFolder);
```

## System Interaction
### setAppInitiated()

The `setAppInitiated()` method set app running by provided name, creating system
file with current pid and handling requested shutdown.

```php
$systemInteraction->setAppInitiated("MyAppName" [,bool $handleShutDown]): bool;
```

### getAppInitiated()

The `getAppInitiated()` get info from system folder to inform if this app was set
as initiated.

```php
$systemInteraction->getAppInitiated(string $appName): bool;
```

### setFunctionOnAppAborted()

The `setFunctionOnAppAborted()` will provide a method where you can add functions
that must be executed before system shut down.

```php
$systemInteraction->setFunctionOnAppAborted(callable $func): bool;
```

### setAborted()

The `setAborted()` is generally used internal, but you could call this function
to manually set app aborted and close script.

```php
$systemInteraction->setAborted(void);
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](https://semver.org/).
This will install the latest supported version:

```bash
$ composer require brunonatali/tools:^1.0
```

This project aims to run on any platform and thus does not require any PHP
extensions, but actually not tested in all environments. If you find a bug, please report.


## License

MIT, see [LICENSE file](LICENSE).

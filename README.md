# Container Delegate
This package contains a [PSR-11](https://www.php-fig.org/psr/psr-11/) compatible container implementation but with a twist. This container is designed as a "delegate" container, meaning you should use it as a fallback for your services you are not configuring manually. You can [learn more about container delegation here](https://github.com/container-interop/container-interop/blob/master/docs/Delegate-lookup-meta.md).

The container has zero configuration options and relies on [interface based auto-wiring conventions](https://github.com/Neunerlei/container-autowiring-declaration). If your service needs parameters or hard wired instances use a compound container, this is not what you want from this implementation. 

In addition to the "zero-configuration" approach the implementation features compiled factories, auto-wiring (duh) and lazy service injection using lazy proxies.

For my projects I normally use the [league container](https://container.thephpleague.com/) which has a really nice configuration interface and a ton of options to work with. Of course, it [supports delegate containers](https://container.thephpleague.com/3.x/delegate-containers).

## Installation
Install this package using composer:

```
composer require neunerlei/container-delegate
```

## Creating the container
Simply create a new container instance and you are good to go:
```php
use Neunerlei\ContainerDelegate\Container;
$container = new Container();
```

## Getting an instance
The container uses PHPs reflection capabilities to automatically wire dependencies for the arguments.
In this example class B requires class A, which will be automatically injected when the instance of B is created.
```php
use Neunerlei\ContainerDelegate\Container;

class A {}

class B {
    public $a;
    public function __construct(A $a) {$this->a = $a;}
}

$container = new Container();
$i = $container->get(B::class);
```

## Getting an instance by interface
A lot of container implementations rely on the developer to map an interface with an implementation.
This container implementation will take an educated guess on what class it should look up.
If your interface name ends with "Interface", the container will automatically try to instantiate a class with the same name but without the "Interface" part.
```php
use Neunerlei\ContainerDelegate\Container;

interface AInterface {}

class A implements AInterface {
}

$container = new Container();
$i = $container->get(AInterface::class);
var_dump($i instanceof A);
```

## Handling default parameters
If your implementation allows for additional parameters that are not directly injectable, the container will use the default
value if it is present.
```php
use Neunerlei\ContainerDelegate\Container;

class A {
    public function __construct(string $foo = "bar", ?Container $container = null) {
        var_dump($foo === "bar");
        var_dump($container instanceof Container);
    }
}

$container = new Container();
$i = $container->get(A::class);
```

## Always return the same instance (Singleton)
Some services should only be instantiated once but distributed for many services (e.g. a database connection). 
While the [league container supports this feature](https://container.thephpleague.com/3.x/definitions/#defining-shared-objects) out of the box, other implementations do not. 
For that reason the container implements the feature itself, but tries to use the compound container if it provides the singleton feature.

To define a singleton service instance in your application you can add the [SingletonInterface](https://github.com/Neunerlei/container-autowiring-declaration#singletoninterface) to it. After that the service will only be instanced once.
```php
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;
use Neunerlei\ContainerDelegate\Container;

class A implements SingletonInterface {
}

$container = new Container();
$i = $container->get(A::class);
var_dump($i instanceof A);
var_dump($i === $container->get(A::class));
```

## Using injection methods
If you want to inject additional services after the __construct() method was executed you would normally have to wire the "set" methods using your container configuration.
By using the [InjectableInterface](https://github.com/Neunerlei/container-autowiring-declaration#injectableinterface) the container will automatically scan all public, non static methods that begin with "inject" and provide the arguments for them.
You can inject multiple instances with a single inject method.
```php
use Neunerlei\ContainerAutoWiringDeclaration\InjectableInterface;
use Neunerlei\ContainerDelegate\Container;

class A {}

class B implements InjectableInterface {
    public $a;
    public function injectA(A $a){
        $this->a = $a;    
    }
}

$container = new Container();
$i = $container->get(B::class);
var_dump($i->a instanceof A);
```

## Lazy loading
Sometimes you want services in your objects that might be required only in certain circumstances. If you have a tiny service that does not cause overhead that is fine in general. If you on the other hand have a bulky service that might even take some time setting up (a db connection for example), you should start thinking on a lazy loading proxy.

This feature injects a tiny wrapper instead of the real implementation into your requesting instance. Only if one of the proxy features is requested, the real instance will be created, meaning you can save a lot of processing time.

The container implementation has a build in support for lazy parameter injection. Every property that has an interface as type and a name that starts with "lazy" will be injected as lazy loading object.
```php
use Neunerlei\ContainerDelegate\Container;

interface AInterface {
    public function foo();
}

class A implements AInterface {
    public function foo(){
        return "foo!";
    }
}

$container = new Container();
$i = $container->get(AInterface::class);
var_dump($i instanceof AnInterface);
var_dump($i instanceof A); // This is FALSE!
var_dump($i->foo());
```

## Compiling the factories
To avoid the overhead of reflecting every object, every time it is required the implementation has the option to compile the factories into php code.

The compilation is done on the fly and only for objects that were required at least once, meaning your container does not know every class or interface it should be able to instantiate in the future. Meaning: Good-Bye "ServiceNotFoundException" every time you forgot to add a Service class to your configuration.yml.

To provide this feature only the factories are compiled as php code and stored in a file that will be expanded every time the container "learns" a new class it has to instantiate. Giving you both speed and dynamic lookups without the configuration hassle.

If you want to use the compiling feature (it's disabled by default) you have to provide an absolute path to a writable directory on your file system, after that the container will handle the rest.
To reset the cached/compiled factories just remove the files in the storage directory.

```php
use Neunerlei\ContainerDelegate\Container;

class A {}

class B {
    public $a;
    public function __construct(A $a) {$this->a = $a;}
}

$container = new Container("/path/to/cache/directory");
$i = $container->get(B::class);
```

## Usage as delegate (league container)
All the examples above use the implementation stand alone, but that is, as stated above NOT THE INTENDED usecase.
It is designed to work as a delegate with another container, that supports manual interface/class mappings, parameters or manual factories.

To use the container I added a simple wrapper that delegates the recursive object resolution and singleton object lookup to the league container.
```php
use Neunerlei\ContainerDelegate\Adapter\LeagueContainerAdapter;

class A {}

class B {
    public $a;
    public function __construct(A $a) {$this->a = $a;}
}

// Create the compound container
$container = new \League\Container\Container();

// Create and register the delegated container
$delegate = new LeagueContainerAdapter("/path/to/cache/directory");
$container->delegate($delegate);

// Make sure the delegate container knows it's parent
$delegate->setContainer($container);
$delegate->setLeagueContainer($container);

// Done, start requesting your stuff :)
$i = $container->get(B::class);
```

## Postcardware
You're free to use this package, but if it makes it to your production environment I highly appreciate you sending me a postcard from your hometown, mentioning which of our package(s) you are using.

You can find my address [here](https://www.neunerlei.eu/). 

Thank you :D 

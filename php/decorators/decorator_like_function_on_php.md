# How to implement decorator-like function as in typescript on PHP

When working with PHP, I wanted to modify the behavior of class methods, aiming to achieve a method decorator similar to Typescript's [method decorators](https://www.typescriptlang.org/docs/handbook/decorators.html#method-decorators). I searched for a decorator-like function but couldn't find anything suitable, so I experimented with creating one. In this blog, I will share my findings and demonstrate how to achieve a <b>method decorator-like</b> function in PHP, similar to those in TypeScript.

## What is decorator

In TypeScript, a decorator is a special kind of declaration that can be attached to a class, method, accessor, property, or parameter. Decorators provide a way to add both annotations and metadata to the target they are attached to.

This is an example of typescript's method decorator in action:

```typescript
export function simpleDecorator(): MethodDecorator {
  return function (target: any, propertyKey: string, descriptor: PropertyDescriptor): void {
    const originalMethod = descriptor.value;
    descriptor.value = async function (...args: any[]) {
      try {
        console.log("[Before trigger method] Hello from decorator");
        const result = await originalMethod.apply(this, args);
        console.log("[After trigger method] Hello from decorator");
        return result;
      } catch (err: any) {
        // error handler
        throw err;
      }
    };
  };
}

class ServiceA {
  @simpleDecorator()
  public methodA() {
    console.log("Hello From Method");
  }
}

const serviceA = new ServiceA();
serviceA.methodA();
// Output:
// [Before trigger method] Hello from decorator
// Hello From Method
// [After trigger method] Hello from decorator
```

## Why decorator?

![why_decorator](../../assets/memes/why_is_decorator_meme.png)

Using decorators offers several benefits that can greatly enhance the efficiency and readability of your code. Here are some key reasons why decorators are useful:

1. Code Reusability and DRY Principle
   - <b>Reusability</b>: Decorators allow you to define reusable pieces of code that can be applied across multiple classes or methods. This eliminates the need to write repetitive logic in multiple places.
   - <b>DRY Principle</b>: By abstracting common functionality into decorators, you adhere to the "Don't Repeat Yourself" principle, reducing code duplication and making your codebase cleaner and easier to maintain.
2. Separation of Concerns

   Decorators help separate cross-cutting concerns (like logging, authorization, validation, etc.) from the business logic. This keeps your core logic focused and straightforward.

3. Enhanced Readability and Maintainability
   - <b>Readability</b>: Using decorators makes it clear what additional behaviors or metadata are associated with a class or method. This can make the code more readable and self-documenting.
   - <b>Maintainability</b>: Since decorators encapsulate specific behaviors, any changes to these behaviors can be made in one place (the decorator itself) rather than scattered throughout the codebase.

## My Findings and Experimentations

When i try to achieve this goal i found some solutions that have each advantages that others dont.

### Solution One

```php
class SimpleDecorator
{
    private $target;
    public function __construct($target)
    {
        $this->target = $target;
    }

    public function __call($method, $args)
    {
        echo "[Decorator:Before] Hello\n";
        $result = call_user_func_array(array($this->target, $method), $args);
        echo "\n[Decorator:After] Hi\n";
        return $result;
    }
}

class ServiceA
{
    public function methodA()
    {
        echo "Method Triggered";
    }
}

$serviceA = new SimpleDecorator(new ServiceA());
$serviceA->methodA();
// Output:
// [Decorator:Before] Hello
// Method Triggered
// [Decorator:After] Hi
```

In order to achieve a decorator-like function we need one of php class's magic method which is `__call` method, this method will be triggered when a method that being called is not defined and bring along the method name and the arguments its sent.

on the example above you can see that our decorator accept a parameter called `$target`, which is expected to be the class to which the decorator is applied.

on the implementation, we try to call `methodA`, one of the methods of class `ServiceA` through decorator `SimpleDecorator` which doesnt have `methodA`, this will trigger `__call` and allowing us to use the informations it sent to call `methodA` from the target class which is `ServiceA`.

one of decorator's characteristic is its can be stacked, let's see how we can stack the decorators:

```php
class AnotherSimpleDecorator
{
    private $target;
    public function __construct($target)
    {
        $this->target = $target;
    }

    public function __call($method, $args)
    {
        echo "\n[AnotherDecorator:Before] Hello\n";
        $result = call_user_func_array(array($this->target, $method), $args);
        echo "[AnotherDecorator:After] Hi\n";
        return $result;
    }
}
$serviceA = new AnotherSimpleDecorator(new SimpleDecorator(new ServiceA()));
$serviceA->methodA();
// Output:
// [AnotherDecorator:Before] Hello
// [Decorator:Before] Hello
// Method Triggered
// [Decorator:After] Hi
// [AnotherDecorator:After] Hi
```

on this example, we stack `AnotherSimpleDecorator` on top of `SimpleDecorator` that applied to `ServiceA`.
When multiple decorators applied, their evaluation is similar to [function composition in mathematics](https://wikipedia.org/wiki/Function_composition).

As such, the following steps are performed when evaluating multiple decorators:

1. The expressions for each decorator are evaluated top-to-bottom (in this case it's left-to-right).
2. The results are then called as functions from bottom-to-top (in this case it's left-to-right).

This solution applies the decorator to all methods, but how can we specify which methods it need to be applied by the decorator or customize the decorator's behavior?. Well, we can do that on this solution but its mean you need to apply the same customization to all decorators and it will break the points on <b>Why decorator?</b> section, that's why solution two come to the rescue.

Solution One full code: [here](./solutionOne.php)

### Solution Two

We got the idea from solution one let's make it more advanced.

```php
class BaseDecorator
{
    private $target;
    private $include_methods = [];
    public function __construct($target, array $include_methods = [])
    {
        $this->target = $target;
        $this->include_methods = $include_methods;
        $this->registerMethods();
    }

    private function registerMethods()
    {
        if (!method_exists($this->target, "handler"))
            throw new Exception("Undefined Decorator Handler \n");

        $methods = get_class_methods($this->target);
        foreach ($methods as $method) {
            $include_method = in_array($method, $this->include_methods);
            if ($include_method)
                runkit7_method_rename(get_class($this->target), $method, "__$method");
        }
    }

    public function __call($method, $args)
    {
        if (!method_exists($this->target, "__$method"))
            throw new Exception("Call attempt on undefined method: $method\n");

        $original_method = function () use ($method, $args) {
            return call_user_func_array(array($this->target, "__$method"), $args);
        };

        return call_user_func_array(array($this->target, "handler"), [$original_method]);
    }
}

class SimpleDecorator extends BaseDecorator
{
    public function __construct($target, array $include_methods = [])
    {
        parent::__construct($target, $include_methods);
    }

    public function handler($original_method)
    {
        echo "[Decorator:Before] Hello\n";
        $result = $original_method();
        echo "\n[Decorator:After] Hi\n";
        return $result;
    }
}
```

now we have `BaseDecorator` this make us have the power to control decorator's behavior. on the second parameter we add `$include_methods` as a configurations parameter to handle which method the decorator need to be applied.

Also, we added `registerMethods` to `BaseDecorator`, this method serves two purpose:

1.  This method ensures that every decorator has a method called `handler`, which will serve as decorator's logic, handler method have a paremeter `$original_method`, representing the original method that the class tryng to call.
2.  This method renames all the methods from the `$target` class that match the `$include_methods` config by prefixing them with "\_\_", with the help of `runkit7` extension. This ensures that when a method from a class with the applied decorator is called, it will trigger the `__call` method.

> **_NOTE:_**
> Im using php 7.4 and runkit7-4.0.0a6

let's see `SimpleDecorator` in action:

```php
class ServiceA extends SimpleDecorator
{
    public function __construct()
    {
        parent::__construct($this, ['methodA']);
    }

    public function methodA()
    {
        echo "MethodA Triggered";
    }

    public function methodB()
    {
        echo "\nMethodB Triggered\n";
    }
}

$serviceA = new ServiceA();
$serviceA->methodA();
// Output:
// [Decorator:Before] Hello
// MethodA Triggered
// [Decorator:After] Hi

$serviceA->methodB();
// Output:
// MethodB Triggered
```

When try to use `SimpleDecorator` we only need the decorator to be applied to `methodA`, hence the output.

<b>Conclusion</b>, Solution two allowing us to have more control at the base level, make our decorators to focus on handling their specific logic and make the decorator declaration much simpler, but it make decorators not stackable.

Solution Two full code: [here](./solutionTwo.php)

### Solution Three

Solution three pretty much the same as solution two but it use the same approach in solution one.

```php
trait BaseDecorator
{
    public $__base_target = [
        "class_name" => "",
        "methods" => [],
    ];
    private $target;
    private $handler;
    private array $method_options = [];
    private array $include_methods = [];

    public function construct($target, callable $handler, array $include_methods = [])
    {
        $this->target = $target;
        $this->handler = $handler;
        $this->include_methods = $include_methods;

        $this->setupBaseTarget();
    }

    private function setupBaseTarget()
    {
        $not_settled = empty(@$this->target->__base_target['class_name'] ?? "");
        if ($not_settled) {
            $this->__base_target = [
                "class_name" => get_class($this->target),
                "methods" => get_class_methods($this->target),
            ];
        } else
            $this->__base_target = $this->target->__base_target;
    }

    public function __call($method, $args)
    {
        $base_target_methods = $this->__base_target['methods'];
        if (!in_array($method, $base_target_methods))
            throw new Exception("Call attempt on undefined method: $method\n");

        $original_method = function () use ($method, $args) {
            return call_user_func_array(array($this->target, $method), $args);
        };

        $use_handler = in_array($method, $this->include_methods);
        return $use_handler ? call_user_func($this->handler, $original_method) : $original_method();
    }
}
```

here is the key different between solution three and two:

1. `handler` as parameter not as method.
2. `BaseDecorator` as a `trait` instead of a `class`, since we gonna use the same approach in solution one, we dont need to rename the method in order to make `__call` being triggered, so we can exclude `registerMethods`.
3. We have `setupBaseTarget`, this method responsible for initializing or copying `$target`'s info.

   when applying multiple decorators for example:

   ```php
     $serviceA = new DecoratorB(new DecoratorA(new ServiceA()));
   ```

   `DecoratorB`'s `$target` is `DecoratorA`, `setupBaseTarget` ensuring all decorators that applied have access to the original target class's info which is `ServiceA`.

let's declare a decorator called `SimpleDecorator` using `BaseDecorator`:

```php
class SimpleDecorator
{
    use BaseDecorator;
    public function __construct($target, array $include_methods = [])
    {
        $handler = function ($original_method) {
            echo "[Decorator:Before] Hello\n";
            $result = $original_method();
            echo "[Decorator:After] Hi\n";
            return $result;
        };

        $this->construct($target, $handler, $include_methods);
    }
}
```

let's use the decarotor:

```php

class ServiceA
{
    public function methodA()
    {
        echo "MethodA Triggered\n";
    }

    public function methodB()
    {
        echo "\nMethodB Triggered\n";
    }
}
$serviceA = new SimpleDecorator(new ServiceA(), ['methodA']);
$serviceA->methodA();
// Output:
// [Decorator:Before] Hello
// MethodA Triggered
// [Decorator:After] Hi

$serviceA->methodB();
// Output:
// MethodB Triggered
```

Stacked decorators in action:

```php
class AnotherSimpleDecorator
{
    use BaseDecorator;
    public function __construct($target, array $include_methods = [])
    {
        $handler = function ($original_method) {
            echo "[AnotherDecorator:Before] Hello\n";
            $result = $original_method();
            echo "[AnotherDecorator:After] Hi\n";
            return $result;
        };

        $this->construct($target, $handler, $include_methods);
    }
}
$serviceA = new AnotherSimpleDecorator(
    new SimpleDecorator(new ServiceA(), ['methodA']),
    ['methodA']
);
$serviceA->methodA();
// Output:
// [AnotherDecorator:Before] Hello
// [Decorator:Before] Hello
// Method Triggered
// [Decorator:After] Hi
// [AnotherDecorator:After] Hi
```
Solution Three full code: [here](./solutionThree.php)

<b>Conclusion</b>, Solution three make decorators stackable but when it come to stacked decorator the declaration become ugly.

with this we cover pretty much all the points on section <b>Why decorator?</b>

That's all I wanted to share. I hope this information helps you in some way.

> **_NOTE:_**
> If you interested in typescript decorator i suggest you check the official documentation [here](https://www.typescriptlang.org/docs/handbook/decorators.html) or you can check my other blog: [Typescrypt: Make your life easier with decorators](https://dev.to/rizkiiqbal36/typescrypt-make-your-life-easier-with-decorators-3ppp)

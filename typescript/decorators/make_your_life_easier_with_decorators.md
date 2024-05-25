# Typescrypt: Make your life easier with decorators

## What is decorator?

In TypeScript, decorator is a special type of declaration that uses the `@` symbol to customize classes and their members, allowing us to add metadata or modify their behavior at runtime.

## Brief History

Decorators were initially introduced as an experimental feature in [TypeScript 1.5](https://github.com/microsoft/TypeScript/releases/tag/v1.5.3) in July 2015, and using them required enabling a specific compiler option called `--experimentalDecorators`.

## Getting Started

Make sure TypeScript is installed on your system, or you can install it locally within your specific project.

In this blog, I'll be using TypeScript v5.4.5, the latest version as of May 24, 2024, installed using npm and configured in Visual Studio Code settings.

To install the latest version of TypeScript and the necessary type definitions for Node.js, use the following command:

```bash
npm i typescript@latest @types/node -D
```

Ensure you configure VS Code to use the TypeScript version installed via npm by adding the following line to your `.vscode/settings.json` file:

```json
{
  "typescript.tsdk": "node_modules/typescript/lib"
}
```

This setting directs VS Code to use the TypeScript language server from the directory installed by npm.

you must enable the `experimentalDecorators` compiler option either via command or your `tsconfig.json` file.

```json
{
  "experimentalDecorators": true
}
```

## Decorator Declaration

The syntax of a decorator is pretty simple, just add the @ operator before the decorator you want to use, then the decorator will be applied to the target:

```typescript
const simpleDecorator: ClassDecorator = function () {
  console.log("hi I am a decorator");
};

@simpleDecorator
class ClassA {}
// Output:
// hi I am a decorator
```

## Decorator Factory

Decorator factory is a function that returns a decorator. It allows you to pass parameters to a decorator. here is how it look like:

```typescript
function addProperty<T>(name: string, value: T) {
  return function (target: { new (...arg: any[]) }): void {
    target.prototype[name] = value;
  };
}

@addProperty<boolean>("isNew", true)
class ClassB {}

console.log(ClassB.prototype);
// Output:
// { isNew: true }
```

Here's a breakdown of a decorator and a decorator factory:

1. Decorator: A special kind of declaration that can be attached to a class, method, accessor, property, or parameter to modify their behavior or add metadata.
2. Decorator Factory: A function that returns a decorator, enabling you to customize the decorator with parameters.

## Examples

There are various types of decorators in TypeScript, such as class decorators, property decorators, Method Decorator, Accessor Decorator, and parameter decorators. In this guide, we'll focus on method decorators.

### Behavior at runtime

as you know decorators are usefull tools that allow us to modify the behavior of classes and their members at runtime. Let's say you have a log decorator with a message parameter, and you want to make sure that the message isn't an empty string. You can accomplish this by add the validation before return the decorator.

```typescript
function simpleDecorator(message: string): MethodDecorator {
  if (message.length === 0) throw Error("Invalid Argument: Doesnt allowed empty string");

  return function (): void {
    console.log("Method triggered");
  };
}
```

This will throw an error as soon as you run the codes:

```typescript
class ServiceA {
  @simpleDecorator("") // Error: Invalid Argument: Doesnt allowed empty string
  public methodA() {}
}
```

This example is valid:

```typescript
class ServiceA {
  @simpleDecorator("a")
  public methodA() {}
}
```

### Decorators are stackable

Decorators can be stacked:

```typescript
const first: MethodDecorator = function () {
  console.log("first");
};
const second: MethodDecorator = function () {
  console.log("second");
};

class ClassA {
  @first
  @second
  methodA() {}
}

// Output:
// second
// first
```

When multiple decorators apply to a single declaration, their evaluation is similar to [function composition in mathematics](https://wikipedia.org/wiki/Function_composition).

As such, the following steps are performed when evaluating multiple decorators on a single declaration in TypeScript:

1. The expressions for each decorator are evaluated top-to-bottom.
2. The results are then called as functions from bottom-to-top.

### Class method as decorator factory

You can also use class methods as decorator factories to create more complex and reusable decorators.

Let's say you have a custom logger class that can print messages with a custom prefix and measure the time taken between different pieces of code.

```typescript
class Logger {
  private performanceTimeLog: {
    timestamp: number;
    // logId: string // You can add custom property here to help you log your messages better
  }[] = [];
  constructor(public readonly instance: string) {}

  public performanceStart = (message: string) => {
    this.performanceTimeLog.push({ timestamp: performance.now() });
    this.log(message);
    return this.performanceTimeLog.length - 1;
  };

  public performanceEnd = (timeStartIndex: number, message: string) => {
    const performanceTime = this.performanceTimeLog[timeStartIndex];
    if (typeof performanceTime === "undefined") throw Error("Define performanceStart first");

    const getTime = (performance.now() - performanceTime.timestamp).toFixed();

    this.printPerformanceMessage(message, getTime);
  };

  private printPerformanceMessage(message: string, time: string) {
    this.log(`${message}, +${time}ms`);
  }

  public log(message: string) {
    process.stdout.write(`[${this.instance}] ${message}\n`);
  }
}

const serviceLogger = new Logger("SERVICE");

const timeStartIndex = serviceLogger.performanceStart("LOG");
serviceLogger.log("Hello there");
serviceLogger.performanceEnd(timeStartIndex, "LOG");
// Output:
// [SERVICE] LOG
// [SERVICE] Hello there
// [SERVICE] LOG, +1ms
```

Let's add a decorator that measures the time taken by the method it is applied to.

```typescript
class Logger {
  ...
  ...
  ...
  public decoratorFunctionPerformance({ message }: { message?: string }) {
    return ((self: typeof this) => {
      return function (target: any, propertyKey: string, descriptor: PropertyDescriptor) {
        const usedMessage = message ?? `EXECUTE ${propertyKey}`;
        const original = descriptor.value;
        descriptor.value = async function (...args: any[]) {
          const performanceIndex = self.performanceStart(`START ${usedMessage}`);
          try {
            const result = await original.apply(this, args);
            return result;
          } catch (err: any) {
            // error handler
            throw err;
          } finally {
            self.performanceEnd(performanceIndex, `END ${usedMessage}`);
          }
        };
      };
    })(this);
  }
}
```

Now we have `decoratorFunctionPerformance`, which is a class method acting as a decorator factory. The method itself is an immediately-invoked function expression [(IIFE)](https://developer.mozilla.org/en-US/docs/Glossary/IIFE), enabling access to the `this` context (Logger instance). This allows us to access the logger instance within our decorator function.

Inside the decorator, we apply the performance timing function both before and after the original method is executed.

Let's see `decoratorFunctionPerformance` in action:

```typescript
const serviceLogger = new Logger("SERVICE");
class ServiceA {
  @serviceLogger.decoratorFunctionPerformance({})
  public async methodA() {
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        resolve(true);
      }, 500);
    });
  }
}
const serviceA = new ServiceA();
serviceA.methodA();
// Output:
// [SERVICE] START EXECUTE methodA
// [SERVICE] END EXECUTE methodA, +503ms
```

On the example above, we applied `decoratorFunctionPerformance` to `methodA` causing it to wait for 500ms before returning the value `true`. Therefore, the execution time will be at least 500ms.

You can see full code [here](./index.ts#L60)

That's all I wanted to share. I hope this information helps you in some way.

Check this blog where i use this example on a more advanced level: [Simple RBAC Using decorator]()

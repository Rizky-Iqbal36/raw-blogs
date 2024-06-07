function simpleDecorator(): MethodDecorator {
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
export {};

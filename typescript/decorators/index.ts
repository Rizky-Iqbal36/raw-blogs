/* -------------------------- Decorator Declaration ------------------------- */
const simpleDecorator: ClassDecorator = function () {
  console.log("hi I am a decorator");
};

@simpleDecorator
class ClassA {}
/* ---------------------- End of Decorator Declaration ---------------------- */

/* ---------------------------- Decorator Factory --------------------------- */
function addProperty<T>(name: string, value: T) {
  return function (target: { new (...arg: any[]) }): void {
    target.prototype[name] = value;
  };
}

@addProperty<boolean>("isNew", true)
class ClassB {}

console.log(ClassB.prototype);
/* ------------------------ End of Decorator Factory ------------------------ */

/* ---------------------- Example: Behavior at runtime ---------------------- */
function simpleDecoratorA(message: string): MethodDecorator {
  if (message.length === 0) throw Error("Invalid Argument: Doesnt allowed empty string");

  return function (): void {
    console.log("Method triggered");
  };
}
// class ServiceA {
//   @simpleDecoratorA("") // Error: Invalid Argument: Doesnt allowed empty string
//   public methodA() {}
// }
class ServiceA {
  @simpleDecoratorA("a")
  public methodA() {}
}
/* ------------------- End of Example: Behavior at runtime ------------------ */

/* -------------------- Example: Decorators are stackable ------------------- */
const first: MethodDecorator = function () {
  console.log("first");
};
const second: MethodDecorator = function () {
  console.log("second");
};

class ClassC {
  @first
  @second
  methodA() {}
}

// Output:
// second
// first
/* ---------------- End of Example: Decorators are stackable ---------------- */

/* --------------- Example: Class method as decorator factory --------------- */
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
    console.log(`[${this.instance}] ${message}`);
  }

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

const serviceLogger = new Logger("SERVICE");

const timeStartIndex = serviceLogger.performanceStart("LOG");
serviceLogger.log("Hello there");
serviceLogger.performanceEnd(timeStartIndex, "LOG");
// Output:
// [SERVICE] LOG
// [SERVICE] Hello there
// [SERVICE] LOG, +1ms

class ServiceB {
  @serviceLogger.decoratorFunctionPerformance({})
  public async methodA() {
    return new Promise((resolve, reject) => {
      setTimeout(() => {
        resolve(true);
      }, 500);
    });
  }
}
const serviceB = new ServiceB();
serviceB.methodA();
// Output:
// [SERVICE] START EXECUTE methodA
// [SERVICE] END EXECUTE methodA, +503ms
/* ------------ End of Example: Class method as decorator factory ----------- */

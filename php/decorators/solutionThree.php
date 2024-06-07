<?php

/* ----------------------------- Base Decorator ----------------------------- */
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
/* -------------------------- End of Base Decorator ------------------------- */

/* ---------------------------- Simple Decorator ---------------------------- */
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
/* ------------------------- End of Simple Decorator ------------------------ */

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

echo "\n---------------------------- Stacked Decorator ---------------------------\n\n";

/* ---------------------------- Stacked Decorator --------------------------- */
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
/* ------------------------ End of Stacked Decorator ------------------------ */

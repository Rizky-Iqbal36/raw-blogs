<?php

/* ----------------------------- Base Decorator ----------------------------- */
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
/* -------------------------- End of Base Decorator ------------------------- */

/* ---------------------------- Simple Decorator ---------------------------- */
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
/* ------------------------- End of Simple Decorator ------------------------ */

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
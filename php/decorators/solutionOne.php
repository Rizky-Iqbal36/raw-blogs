<?php

/* ---------------------------- Simple Decorator ---------------------------- */
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
/* ------------------------- End of Simple Decorator ------------------------ */

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

echo "\n----------------------------------- === ----------------------------------\n";

/* ---------------------------- Stacked Decorator --------------------------- */
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
// [AnotherDecorator:Before] Hello
// [Decorator:Before] Hello
// Method Triggered
// [Decorator:After] Hi
// [AnotherDecorator:After] Hi
/* ------------------------ End of Stacked Decorator ------------------------ */
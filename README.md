PHP Process Pool
================

[![License](https://poser.pugx.org/gemorroj/process-pool/license)](https://packagist.org/packages/gemorroj/process-pool)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/process-pool/v/stable)](https://packagist.org/packages/gemorroj/process-pool)
[![Continuous Integration](https://github.com/Gemorroj/process-pool/workflows/Continuous%20Integration/badge.svg)](https://github.com/Gemorroj/process-pool/actions?query=workflow%3A%22Continuous+Integration%22)

PHP Process Pool is a simple process pool using Symfony Process

### System requirements:
- PHP >= 8.4
- proc_open

### Installation:
```bash
composer require gemorroj/process-pool
```

### Example:
```php
use ProcessPool\ProcessPool;
use Symfony\Component\Process\Process;

function processGenerator(): \Generator {
    for ($i = 0; $i < 10; $i++) {
        yield new Process(['sleep', $i]);
    }
}

$pool = new ProcessPool(processGenerator());
$pool->wait(); // run all processes and wait for completion
```

`run()` is a streaming alternative to `wait()`: it yields a `ProcessFinished`
event as soon as each process finishes.

```php
foreach ($pool->run() as $event) {
    echo $event->getProcess()->getOutput();
}
```

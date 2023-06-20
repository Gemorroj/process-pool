PHP Process Pool
================

[![License](https://poser.pugx.org/gemorroj/process-pool/license)](https://packagist.org/packages/gemorroj/process-pool)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/process-pool/v/stable)](https://packagist.org/packages/gemorroj/process-pool)
[![Continuous Integration](https://github.com/Gemorroj/process-pool/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/Gemorroj/process-pool/actions?query=workflow%3A%22Continuous+Integration%22)

PHP Process Pool is a simple process pool using Symfony Process

### System requirements:
- PHP >= 8.2
- proc_open

### Installation:
```bash
composer require gemorroj/process-pool
```

### Example:
```php
use ProcessPool\ProcessPool;
use Symfony\Component\Process\Process;

function processGenerator(int $count): \Generator {
    for ($i = 0; $i < 10; $i++) {
        yield new Process(['sleep', $i]);
    }
}

$processes = processGenerator(10);
$pool = new ProcessPool($processes);
$pool->wait();
```

<?php

declare(strict_types=1);

use Modules\ERP\Tests\TestCase;

pest()->extend(TestCase::class)
    ->in(__DIR__ . '/Integration', __DIR__ . '/Feature');

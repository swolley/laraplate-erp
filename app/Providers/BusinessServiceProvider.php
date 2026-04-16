<?php

declare(strict_types=1);

namespace Modules\Business\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class BusinessServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Business';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'business';
}

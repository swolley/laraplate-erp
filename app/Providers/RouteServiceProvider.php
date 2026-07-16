<?php

declare(strict_types=1);

namespace Modules\ERP\Providers;

use Modules\Core\Overrides\RouteServiceProvider as ServiceProvider;

final class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'ERP';
}

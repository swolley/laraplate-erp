<?php

declare(strict_types=1);

namespace Modules\ERP\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class ERPPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'ERP';
    }

    public function getId(): string
    {
        return 'erp';
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

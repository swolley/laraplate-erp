<?php

declare(strict_types=1);

namespace Modules\ERP\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Support\ModuleColor;

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

    /**
     * Own the module's presence in the panel instead of having the panel list every
     * module: the navigation group, its icon and the module colour (registered under the
     * module id, so widgets can paint with it) are all declared here.
     */
    public function afterRegister(Panel $panel): void
    {
        $color = ModuleColor::filament($this->getModuleName());

        if ($color !== null) {
            $panel->colors([$this->getId() => $color]);
        }

        $panel->navigationGroups([
            NavigationGroup::make()->label('ERP - Master data')->icon(Heroicon::OutlinedBuildingOffice),
            NavigationGroup::make()->label('ERP - Sales')->icon(Heroicon::OutlinedShoppingCart),
            NavigationGroup::make()->label('ERP - Purchasing')->icon(Heroicon::OutlinedTruck),
            NavigationGroup::make()->label('ERP - Inventory')->icon(Heroicon::OutlinedArchiveBox),
            NavigationGroup::make()->label('ERP - Accounting')->icon(Heroicon::OutlinedCalculator),
            NavigationGroup::make()->label('ERP - Treasury')->icon(Heroicon::OutlinedBanknotes),
        ]);
    }
}

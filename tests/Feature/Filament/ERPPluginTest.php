<?php

declare(strict_types=1);

use Filament\Panel;
use Modules\ERP\Filament\ERPPlugin;

it('exposes erp plugin metadata and boots without error', function (): void {
    $plugin = new ERPPlugin();

    expect($plugin->getId())->toBe('erp')
        ->and($plugin->getModuleName())->toBe('ERP');

    $plugin->boot(Panel::make('erp-test'));
});

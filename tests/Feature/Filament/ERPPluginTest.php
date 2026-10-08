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

it('registers every navigation group used by the erp resources and pages', function (): void {
    $panel = Panel::make('erp-test');
    (new ERPPlugin())->afterRegister($panel);

    $registered = collect($panel->getNavigationGroups())
        ->map(fn ($group): string => $group->getLabel())
        ->all();

    $used = collect(glob(module_path('ERP', 'app/Filament/{Resources/*,Pages}/*.php'), GLOB_BRACE))
        ->map(fn (string $file): string => 'Modules\\ERP\\Filament\\' . str_replace(['/', '.php'], ['\\', ''], mb_substr($file, mb_strlen(module_path('ERP', 'app/Filament/')))))
        ->filter(fn (string $class): bool => class_exists($class) && property_exists($class, 'navigationGroup'))
        ->map(function (string $class): ?string {
            $property = new ReflectionProperty($class, 'navigationGroup');

            return $property->getValue();
        })
        ->filter()
        ->unique()
        ->values()
        ->all();

    expect($used)->not->toBeEmpty()
        ->and(array_diff($used, $registered))->toBe([]);
});

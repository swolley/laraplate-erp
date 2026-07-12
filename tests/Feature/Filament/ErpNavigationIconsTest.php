<?php

declare(strict_types=1);

use Filament\Pages\Page;
use Filament\Resources\Resource;

it('uses unique navigation icons across ERP filament resources and pages', function (): void {
    $icon_usage = [];

    $resource_files = glob(base_path('Modules/ERP/app/Filament/Resources/*/*Resource.php')) ?: [];
    $page_files = glob(base_path('Modules/ERP/app/Filament/Pages/*.php')) ?: [];

    foreach ([...$resource_files, ...$page_files] as $file_path) {
        $class = pathToErpFilamentClass($file_path);

        if (! class_exists($class)) {
            continue;
        }

        if (! is_subclass_of($class, Resource::class) && ! is_subclass_of($class, Page::class)) {
            continue;
        }

        $icon = $class::getNavigationIcon();

        if ($icon === null) {
            continue;
        }

        $icon_key = $icon instanceof BackedEnum ? $icon->value : (string) $icon;
        $icon_usage[$icon_key] ??= [];
        $icon_usage[$icon_key][] = $class;
    }

    $duplicates = array_filter($icon_usage, static fn (array $classes): bool => count($classes) > 1);

    expect($duplicates)->toBeEmpty(
        'Duplicate ERP navigation icons found: ' . json_encode($duplicates, JSON_THROW_ON_ERROR),
    );
});

function pathToErpFilamentClass(string $file_path): string
{
    $relative_path = str_replace(base_path('Modules/ERP/app/'), '', $file_path);
    $relative_path = str_replace(['/', '.php'], ['\\', ''], $relative_path);

    return 'Modules\\ERP\\' . $relative_path;
}

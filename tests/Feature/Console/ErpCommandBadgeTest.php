<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('uses a distinct yellow ERP badge with icon on every erp artisan command', function (): void {
    $badge = '<fg=yellow>(💼 Modules\ERP)</fg=yellow>';
    $commands = array_filter(
        Artisan::all(),
        static fn (Command $command, string $name): bool => str_starts_with($name, 'erp:'),
        ARRAY_FILTER_USE_BOTH,
    );

    expect($commands)->not->toBeEmpty();

    foreach ($commands as $name => $command) {
        expect($command->getDescription())
            ->toContain($badge)
            ->and($name)->toStartWith('erp:');
    }
});

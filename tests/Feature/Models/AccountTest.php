<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Models\Account;

it('defines parent and children relationships', function (): void {
    $account = new Account;

    expect($account->parent())->toBeInstanceOf(BelongsTo::class)
        ->and($account->children())->toBeInstanceOf(HasMany::class)
        ->and($account->parent()->getRelated())->toBeInstanceOf(Account::class);
});

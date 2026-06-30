<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\PriceList;
use Modules\ERP\Models\PriceListItem;

it('defines taxonomy and price list relationships', function (): void {
    $item = new PriceListItem;

    expect($item->taxonomy())->toBeInstanceOf(BelongsTo::class)
        ->and($item->taxonomy()->getRelated())->toBeInstanceOf(Activity::class)
        ->and($item->price_list())->toBeInstanceOf(BelongsTo::class)
        ->and($item->price_list()->getRelated())->toBeInstanceOf(PriceList::class);
});

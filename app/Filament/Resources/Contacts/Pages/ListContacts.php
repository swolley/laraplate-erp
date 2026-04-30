<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Contacts\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Override;

final class ListContacts extends ListRecords
{
    #[Override]
    protected static string $resource = ContactResource::class;
}

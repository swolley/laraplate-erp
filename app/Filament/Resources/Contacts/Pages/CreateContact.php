<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Contacts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Modules\ERP\Models\Contact;
use Override;

final class CreateContact extends CreateRecord
{
    #[Override]
    protected static string $resource = ContactResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $customer_ids = $data['customer_ids'] ?? [];
        unset($data['customer_ids']);

        /** @var Contact $record */
        $record = Contact::query()->create($data);
        $record->customers()->sync($customer_ids);

        return $record;
    }
}

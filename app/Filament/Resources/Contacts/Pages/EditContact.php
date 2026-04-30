<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Contacts\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\Contacts\ContactResource;
use Modules\ERP\Models\Contact;
use Override;

final class EditContact extends EditRecord
{
    #[Override]
    protected static string $resource = ContactResource::class;

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Contact $contact */
        $contact = $this->record;
        $data['customer_ids'] = $contact->customers()->pluck('id')->all();

        return $data;
    }

    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $customer_ids = $data['customer_ids'] ?? [];
        unset($data['customer_ids']);

        /** @var Contact $record */
        $record->update($data);
        $record->customers()->sync($customer_ids);

        return $record;
    }
}

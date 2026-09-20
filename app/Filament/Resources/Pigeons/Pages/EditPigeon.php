<?php

namespace App\Filament\Resources\Pigeons\Pages;

use App\Filament\Resources\Pigeons\PigeonResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPigeon extends EditRecord
{
    protected static string $resource = PigeonResource::class;

    protected function afterSave(): void
    {
        $this->data['lock_version'] = $this->getRecord()->lock_version;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->expectedVersion = (int) ($data['lock_version'] ?? 0);
        unset($data['lock_version']);
        try {
            $record->fill($data)->save();

            return $record;
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }
}

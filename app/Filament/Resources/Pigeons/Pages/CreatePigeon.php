<?php

namespace App\Filament\Resources\Pigeons\Pages;

use App\Filament\Resources\Pigeons\PigeonResource;
use App\Models\Pigeon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePigeon extends CreateRecord
{
    protected static string $resource = PigeonResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return Pigeon::create($data);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }
}

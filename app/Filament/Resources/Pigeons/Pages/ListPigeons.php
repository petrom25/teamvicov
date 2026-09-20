<?php

namespace App\Filament\Resources\Pigeons\Pages;

use App\Filament\Resources\Pigeons\PigeonResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPigeons extends ListRecords
{
    protected static string $resource = PigeonResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Export CSV')->url(route('pigeons.export')), CreateAction::make()->label('Adaugă porumbel')];
    }
}

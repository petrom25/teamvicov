<?php

namespace App\Filament\Resources\Compartments\Pages;

use App\Filament\Resources\Compartments\CompartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompartments extends ManageRecords
{
    protected static string $resource = CompartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

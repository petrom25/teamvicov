<?php

namespace App\Filament\Resources\Compartments;

use App\Models\Compartment;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CompartmentResource extends Resource
{
    protected static ?string $model = Compartment::class;

    protected static ?string $pluralModelLabel = 'Compartimente';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurare';

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Denumire')->required()->maxLength(255)->unique(ignoreRecord: true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Denumire')->searchable()])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCompartments::route('/')];
    }
}

<?php

namespace App\Filament\Resources\Pigeons;

use App\Models\Pigeon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PigeonResource extends Resource
{
    protected static ?string $model = Pigeon::class;

    protected static ?string $modelLabel = 'porumbel';

    protected static ?string $pluralModelLabel = 'Porumbei';

    protected static ?string $navigationLabel = 'Registrul porumbeilor';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 1;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $parent = fn (string $field, string $label, string $sex) => Select::make($field)->label($label)->searchable()
            ->getSearchResultsUsing(fn (string $search) => Pigeon::whereIn('sex', [$sex, 'U'])->where(fn ($q) => $q->where('ring', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'))->limit(50)->get()->pluck('label', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => Pigeon::find($value)?->label)->helperText('Caută după serie sau nume. Poți adăuga strămoșii ca fișe de referință.');
        $upload = fn (string $field, string $label) => FileUpload::make($field)->label($label)->disk('local')->directory('pigeons/'.$field)->visibility('private')
            ->multiple()->maxFiles(12)->maxSize(10240)->preventFilePathTampering()->previewable(false)
            ->getUploadedFileUsing(fn (string $file) => ['name' => basename($file), 'size' => Storage::disk('local')->exists($file) ? Storage::disk('local')->size($file) : 0, 'type' => 'application/octet-stream', 'url' => null]);

        return $schema->components([
            Hidden::make('lock_version')->default(1),
            Section::make('Identitate')->schema([
                TextInput::make('ring')->label('Serie inel')->maxLength(100)->helperText('Păstrăm zerourile de la început. Poate lipsi la un strămoș.'),
                TextInput::make('name')->label('Nume')->maxLength(255),
                Select::make('sex')->label('Sex')->options(['M' => 'Mascul', 'F' => 'Femelă', 'U' => 'Necunoscut'])->default('U')->required(),
                TextInput::make('birth_year')->label('An naștere')->numeric()->minValue(1900)->maxValue(date('Y') + 1),
                TextInput::make('country')->label('Țară / federație')->maxLength(10), TextInput::make('color')->label('Culoare')->maxLength(255),
                TextInput::make('origin')->label('Origine / crescător')->maxLength(255),
                Toggle::make('is_owned')->label('Porumbel propriu')->default(true)->helperText('Dezactivează pentru un strămoș de referință.'),
            ])->columns(2),
            Section::make('Evidență')->schema([
                Select::make('status')->label('Stare')->options(Pigeon::STATUSES)->default('in_loft')->required(),
                Select::make('category_id')->label('Categorie')->relationship('category', 'name')->searchable()->preload(),
                Select::make('compartment_id')->label('Compartiment')->relationship('compartment', 'name')->searchable()->preload(),
                Toggle::make('archived')->label('Arhivat')->helperText('Fișa și legăturile genealogice rămân păstrate.'),
            ])->columns(2),
            Section::make('Părinți')->schema([$parent('father_id', 'Tată', 'M'), $parent('mother_id', 'Mamă', 'F')])->columns(2),
            Section::make('Fotografii și documente private')->schema([
                $upload('photos', 'Fotografii (inclusiv ochi)')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->reorderable(),
                $upload('documents', 'Pedigree original / documente PDF')->acceptedFileTypes(['application/pdf']),
            ]),
            Section::make('Note')->schema([Textarea::make('results')->label('Rezultate')->rows(4)->maxLength(20000), Textarea::make('notes')->label('Note private')->rows(4)->maxLength(20000)]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ring')->label('Serie')->searchable()->sortable()->placeholder('Fără serie'),
            TextColumn::make('name')->label('Nume')->searchable(),
            TextColumn::make('sex')->label('Sex')->formatStateUsing(fn ($state) => ['M' => 'Mascul', 'F' => 'Femelă', 'U' => 'Necunoscut'][$state]),
            IconColumn::make('is_owned')->label('Propriu')->boolean(),
            TextColumn::make('status')->label('Stare')->formatStateUsing(fn ($state) => Pigeon::STATUSES[$state])->badge(),
            TextColumn::make('category.name')->label('Categorie'), TextColumn::make('compartment.name')->label('Compartiment'),
            IconColumn::make('archived')->label('Arhivat')->boolean()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            TernaryFilter::make('is_owned')->label('Porumbei proprii')->trueLabel('Proprii')->falseLabel('Strămoși de referință'),
            TernaryFilter::make('archived')->label('Arhivate')->default(false),
            SelectFilter::make('status')->label('Stare')->options(Pigeon::STATUSES),
            SelectFilter::make('category')->label('Categorie')->relationship('category', 'name'),
        ])->recordActions([
            EditAction::make()->label('Editează'),
            Action::make('pedigree')->label('Fișă și pedigree')->icon('heroicon-o-document-text')->url(fn (Pigeon $record) => route('pigeons.pedigree', $record))->openUrlInNewTab(),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPigeons::route('/'), 'create' => Pages\CreatePigeon::route('/create'), 'edit' => Pages\EditPigeon::route('/{record}/edit')];
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\LoftSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class LoftSettings extends Page
{
    protected static ?string $title = 'Setările crescătoriei';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurare';

    protected string $view = 'filament.pages.loft-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(LoftSetting::findOrFail(1)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->model(LoftSetting::find(1))->components([
            TextInput::make('name')->label('Numele crescătoriei')->required()->maxLength(255),
            FileUpload::make('logo')->label('Logo')->disk('local')->directory('loft')->visibility('private')->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(2048)->preventFilePathTampering()->previewable(false)->getUploadedFileUsing(fn (string $file) => ['name' => basename($file), 'size' => 0, 'type' => 'image/png', 'url' => null]),
            Textarea::make('pedigree_header')->label('Antet pedigree: crescător, adresă, contact')->maxLength(1500)->rows(4),
        ]);
    }

    public function save(): void
    {
        LoftSetting::findOrFail(1)->update($this->form->getState());
        Notification::make()->title('Setări salvate')->success()->send();
    }
}

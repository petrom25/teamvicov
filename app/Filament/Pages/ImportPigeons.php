<?php

namespace App\Filament\Pages;

use App\Services\WooCommerceImport;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

class ImportPigeons extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Import WooCommerce';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.import-pigeons';

    public $file;

    #[Locked]
    public array $preview = [];

    #[Locked]
    public ?string $digest = null;

    public function inspect(): void
    {
        $this->validate(['file' => 'required|file|max:2048']);
        $this->preview = app(WooCommerceImport::class)->preview($this->file->getRealPath());
        $this->digest = hash_file('sha256', $this->file->getRealPath());
    }

    public function runImport(): void
    {
        $this->validate(['file' => 'required|file|max:2048']);
        if (! $this->digest || ! hash_equals($this->digest, hash_file('sha256', $this->file->getRealPath()))) {
            $this->addError('file', 'Previzualizează fișierul înainte de import.');

            return;
        }
        $result = app(WooCommerceImport::class)->import($this->file->getRealPath());
        $this->file->delete();
        $this->file = null;
        $this->preview = [];
        $this->digest = null;
        Notification::make()->title("Import finalizat: {$result['created']} fișe noi, {$result['skipped']} rânduri omise.")->success()->send();
    }
}

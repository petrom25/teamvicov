<?php

namespace Tests\Feature;

use App\Filament\Pages\LoftSettings;
use App\Filament\Resources\Pigeons\Pages\CreatePigeon;
use App\Filament\Resources\Pigeons\Pages\EditPigeon;
use App\Models\LoftSetting;
use App\Models\Pigeon;
use App\Models\User;
use App\Services\WooCommerceImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RegistryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_admin = true;
        $u->save();

        return $u;
    }

    public function test_private_routes_require_login_and_admin(): void
    {
        $p = Pigeon::create(['ring' => 'RO26-00001']);
        foreach (['/admin', '/registry/export', "/registry/{$p->id}/pedigree", "/registry/{$p->id}/files/photos/0", '/registry-logo'] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        $this->actingAs(User::factory()->create())->get("/registry/{$p->id}/pedigree")->assertForbidden();
        $this->get('/admin/pigeons')->assertForbidden();
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_admin_can_render_all_pages_and_pedigree(): void
    {
        $this->actingAs($this->admin());
        $p = Pigeon::create(['ring' => 'RO26-00001']);
        foreach (['/admin', '/admin/pigeons', '/admin/pigeons/create', "/admin/pigeons/{$p->id}/edit", '/admin/categories', '/admin/compartments', '/admin/loft-settings', '/admin/import-pigeons', "/registry/{$p->id}/pedigree?generations=5"] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_livewire_create_and_edit(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(CreatePigeon::class)->fillForm(['ring' => 'RO26-00007', 'sex' => 'M', 'status' => 'in_loft', 'is_owned' => true])->call('create')->assertHasNoFormErrors();
        $p = Pigeon::where('ring', 'RO26-00007')->firstOrFail();
        Livewire::test(EditPigeon::class, ['record' => $p->id])->fillForm(['name' => 'Test'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Test', $p->fresh()->name);
    }

    public function test_normalized_duplicate_preserves_original_ring(): void
    {
        $p = Pigeon::create(['ring' => 'RO26-00001']);
        $this->assertSame('RO26-00001', $p->ring);
        $this->expectException(ValidationException::class);
        Pigeon::create(['ring' => 'ro26 00001']);
    }

    public function test_cycles_are_rejected_and_shared_ancestors_allowed(): void
    {
        $grand = Pigeon::create(['name' => 'Strămoș', 'sex' => 'M', 'is_owned' => false]);
        $f = Pigeon::create(['name' => 'Tată', 'sex' => 'M', 'father_id' => $grand->id]);
        $m = Pigeon::create(['name' => 'Mamă', 'sex' => 'F', 'father_id' => $grand->id]);
        $child = Pigeon::create(['name' => 'Pui', 'sex' => 'M', 'father_id' => $f->id, 'mother_id' => $m->id]);
        $this->assertSame($grand->id, $child->mother->father_id);
        $this->expectException(ValidationException::class);
        $grand->father_id = $child->id;
        $grand->save();
    }

    public function test_incompatible_parent_sex_is_rejected(): void
    {
        $f = Pigeon::create(['name' => 'Femelă', 'sex' => 'F']);
        $this->expectException(ValidationException::class);
        Pigeon::create(['name' => 'Pui', 'father_id' => $f->id]);
    }

    public function test_parent_role_prevents_incompatible_sex_edit(): void
    {
        $f = Pigeon::create(['name' => 'Tată', 'sex' => 'M']);
        Pigeon::create(['name' => 'Pui', 'father_id' => $f->id]);
        $this->expectException(ValidationException::class);
        $f->sex = 'F';
        $f->save();
    }

    public function test_stale_edits_are_rejected_and_status_history_preserved(): void
    {
        $p = Pigeon::create(['name' => 'Pui']);
        $stale = $p->fresh();
        $p->status = 'lost';
        $p->save();
        $this->assertSame(2, DB::table('status_changes')->where('pigeon_id', $p->id)->count());
        $this->expectException(ValidationException::class);
        $stale->name = 'Stale';
        $stale->save();
    }

    public function test_files_are_private_and_unlisted_path_is_unavailable(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pigeons/photos/test.jpg', 'test');
        $p = Pigeon::create(['name' => 'Foto', 'photos' => ['pigeons/photos/test.jpg']]);
        $this->get('/storage/pigeons/photos/test.jpg')->assertNotFound();
        $this->actingAs($this->admin())->get("/registry/{$p->id}/files/photos/0")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get("/registry/{$p->id}/files/photos/1")->assertNotFound();
    }

    public function test_import_preview_does_not_write_and_repeated_import_is_safe(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "Nume,Nume atribut 1,Valoare (valori) atribut 1,Publicat\nRO22-0088803,Sex,Femelă,1\nRO22-0088803,Sex,Femelă,1\n");
        try {
            $s = new WooCommerceImport;
            $preview = $s->preview($path);
            $this->assertSame(0, Pigeon::count());
            $this->assertSame('F', $preview[0]['data']['sex']);
            $this->assertSame(['created' => 1, 'skipped' => 1], $s->import($path));
            $this->assertSame(['created' => 0, 'skipped' => 2], $s->import($path));
            $this->assertSame('RO22-0088803', Pigeon::first()->ring);
        } finally {
            unlink($path);
        }
    }

    public function test_edit_twice_keeps_version_and_stale_form_cannot_overwrite(): void
    {
        $this->actingAs($this->admin());
        $p = Pigeon::create(['name' => 'Original']);
        $page = Livewire::test(EditPigeon::class, ['record' => $p->id]);
        $page->fillForm(['name' => 'First'])->call('save')->assertHasNoFormErrors();
        $page->fillForm(['name' => 'Second'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Second', $p->fresh()->name);
        $fresh = $p->fresh();
        $fresh->name = 'Other window';
        $fresh->save();
        $page->fillForm(['name' => 'Outdated'])->call('save')->assertHasFormErrors(['ring']);
        $this->assertSame('Other window', $p->fresh()->name);
    }

    public function test_photo_upload_and_settings_save(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $p = Pigeon::create(['name' => 'Foto']);
        Livewire::test(EditPigeon::class, ['record' => $p->id])->fillForm(['photos' => [UploadedFile::fake()->image('pigeon.jpg')]])->call('save')->assertHasNoFormErrors();
        $path = $p->fresh()->photos[0];
        Storage::disk('local')->assertExists($path);
        $this->get("/registry/{$p->id}/files/photos/0")->assertOk();
        Livewire::test(LoftSettings::class)->fillForm(['name' => 'Team Vicov Test', 'pedigree_header' => 'Antet'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Team Vicov Test', LoftSetting::find(1)->name);
    }
}

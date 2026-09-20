<?php

use App\Http\Middleware\RequireAdministrator;
use App\Models\LoftSetting;
use App\Models\Pigeon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::redirect('/', '/admin');
Route::middleware(RequireAdministrator::class)->group(function () {
    Route::get('/registry/export', function () {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $fields = ['id', 'ring', 'name', 'country', 'birth_year', 'sex', 'color', 'origin', 'is_owned', 'status', 'father_id', 'mother_id', 'category_id', 'compartment_id', 'results', 'notes', 'archived'];
            fputcsv($out, $fields, ',', '"', '');
            foreach (Pigeon::orderBy('id')->cursor() as $p) {
                fputcsv($out, array_map(function ($f) use ($p) {
                    $value = (string) $p->$f;

                    return preg_match('/^[=+@\-\t\r\n]/u', $value) ? "'".$value : $value;
                }, $fields), ',', '"', '');
            }
            fclose($out);
        }, 'teamvicov-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('pigeons.export');
    Route::get('/registry/{pigeon}/pedigree', function (Pigeon $pigeon) {
        $depth = (int) request('generations', 3);
        abort_unless(in_array($depth, [3, 4, 5]), 422);

        return view('pedigree.show', ['pigeon' => $pigeon, 'depth' => $depth, 'settings' => LoftSetting::findOrFail(1), 'history' => DB::table('status_changes')->where('pigeon_id', $pigeon->id)->orderByDesc('id')->get()]);
    })->name('pigeons.pedigree');
    Route::get('/registry/{pigeon}/files/{kind}/{index}', function (Pigeon $pigeon, string $kind, int $index) {
        abort_unless(in_array($kind, ['photos', 'documents']), 404);
        $path = ($pigeon->$kind ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), ['X-Content-Type-Options' => 'nosniff'], $kind === 'photos' ? 'inline' : 'attachment');
    })->whereNumber('index')->name('pigeons.file');
    Route::get('/registry-logo', function () {
        $path = LoftSetting::findOrFail(1)->logo;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
    })->name('loft.logo');
});

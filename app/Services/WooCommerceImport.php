<?php

namespace App\Services;

use App\Models\Pigeon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WooCommerceImport
{
    public function preview(string $path): array
    {
        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'Fișier CSV gol.']);
        }
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        if (! in_array('Nume', $header)) {
            throw ValidationException::withMessages(['file' => 'Este necesar exportul WooCommerce în română, cu coloana Nume.']);
        }
        $rows = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $line++;
            if (count($rows) >= 1000) {
                throw ValidationException::withMessages(['file' => 'Maximum 1000 de rânduri per import.']);
            }
            if (count($cells) !== count($header)) {
                $rows[] = ['line' => $line, 'error' => 'Număr de coloane incorect', 'data' => null];

                continue;
            }
            $row = array_combine($header, $cells);
            $ring = trim($row['Nume']);
            $key = Pigeon::ringKey($ring);
            $error = null;
            if (! $key || strlen($ring) > 100) {
                $error = 'Serie absentă sau prea lungă';
            } elseif (isset($seen[$key]) || Pigeon::where('ring_key', $key)->exists()) {
                $error = 'Serie duplicată — se omite';
            }
            if ($key) {
                $seen[$key] = true;
            }
            $attrs = [];
            foreach ($row as $k => $v) {
                if (preg_match('/^Nume atribut (\d+)$/u', $k, $m)) {
                    $attrs[mb_strtolower(trim($v))] = $row['Valoare (valori) atribut '.$m[1]] ?? '';
                }
            }
            $sex = mb_strtolower(trim($attrs['sex'] ?? ''));
            $data = ['ring' => $ring, 'sex' => str_contains($sex, 'mascul') ? 'M' : (str_contains($sex, 'fem') ? 'F' : 'U'), 'color' => $attrs['culoare'] ?? null, 'notes' => trim(strip_tags(($row['Descriere scurtă'] ?? '')."\n".($row['Descriere'] ?? ''))), 'is_owned' => true, 'status' => 'in_loft'];
            if (preg_match('/^([A-Z]+)(\d{2})-/', $ring, $match)) {
                $data['country'] = $match[1];
                $year = 2000 + (int) $match[2];
                $data['birth_year'] = $year > (int) date('Y') + 1 ? $year - 100 : $year;
            }
            $rows[] = ['line' => $line, 'error' => $error, 'data' => $data, 'image_count' => count(array_filter(explode(',', $row['Imagini'] ?? '')))];
        }
        fclose($h);

        return $rows;
    }

    public function import(string $path): array
    {
        return DB::transaction(function () use ($path) {
            DB::table('registry_locks')->where('id', 1)->lockForUpdate()->first();
            $rows = $this->preview($path);
            $created = 0;
            $skipped = 0;
            foreach ($rows as $row) {
                if ($row['error']) {
                    $skipped++;

                    continue;
                }Pigeon::create($row['data']);
                $created++;
            }

            return compact('created', 'skipped');
        });
    }
}

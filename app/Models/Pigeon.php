<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Pigeon extends Model
{
    protected $attributes = ['sex' => 'U', 'status' => 'in_loft', 'is_owned' => true, 'archived' => false];

    protected $guarded = ['id', 'ring_key', 'lock_version'];

    public ?int $expectedVersion = null;

    public const STATUSES = ['in_loft' => 'În crescătorie', 'loaned' => 'Împrumutat', 'transferred' => 'Transferat', 'lost' => 'Pierdut', 'deceased' => 'Decedat'];

    protected function casts(): array
    {
        return ['is_owned' => 'boolean', 'archived' => 'boolean', 'photos' => 'array', 'documents' => 'array'];
    }

    public function father(): BelongsTo
    {
        return $this->belongsTo(self::class, 'father_id');
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class);
    }

    public function getLabelAttribute(): string
    {
        return ($this->ring ?: 'Fără serie #'.$this->id).($this->name ? ' · '.$this->name : '');
    }

    public static function ringKey(?string $ring): ?string
    {
        return filled($ring) ? mb_strtoupper(preg_replace('/[\s\-]+/u', '', trim($ring))) : null;
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options) {
            // Serialize genealogy writes, including edits of different records.
            DB::table('registry_locks')->where('id', 1)->lockForUpdate()->first();
            $fresh = $this->exists ? self::findOrFail($this->id) : null;
            if ($fresh && ($this->expectedVersion ?? (int) $this->getOriginal('lock_version')) !== (int) $fresh->lock_version) {
                throw ValidationException::withMessages(['ring' => 'Fișa a fost modificată în altă fereastră. Reîncarcă înainte de salvare.']);
            }
            $this->ring = filled($this->ring) ? trim($this->ring) : null;
            $this->ring_key = self::ringKey($this->ring);
            if ($this->ring_key && self::where('ring_key', $this->ring_key)->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))->exists()) {
                throw ValidationException::withMessages(['ring' => 'Această serie există deja în registru.']);
            }
            Validator::make($this->attributes, [
                'ring' => ['nullable', 'string', 'max:100'], 'ring_key' => ['nullable', Rule::unique('pigeons', 'ring_key')->ignore($this->id)],
                'name' => ['nullable', 'string', 'max:255'], 'sex' => ['required', Rule::in(['M', 'F', 'U'])],
                'birth_year' => ['nullable', 'integer', 'between:1900,'.(date('Y') + 1)],
                'status' => ['required', Rule::in(array_keys(self::STATUSES))],
                'category_id' => ['nullable', 'exists:categories,id'], 'compartment_id' => ['nullable', 'exists:compartments,id'],
            ])->validate();
            if (! $this->ring && ! $this->name) {
                throw ValidationException::withMessages(['name' => 'Introdu o serie sau un nume pentru identificare.']);
            }
            if ($this->father_id && $this->father_id == $this->mother_id) {
                throw ValidationException::withMessages(['mother_id' => 'Părinții trebuie să fie diferiți.']);
            }
            foreach (['father_id' => 'M', 'mother_id' => 'F'] as $field => $sex) {
                if (! $this->$field) {
                    continue;
                }
                $parent = self::find($this->$field);
                if (! $parent || ! in_array($parent->sex, [$sex, 'U'])) {
                    throw ValidationException::withMessages([$field => 'Părinte inexistent sau sex incompatibil.']);
                }
                $queue = [$parent->id];
                $visited = [];
                while ($queue) {
                    $id = array_pop($queue);
                    if ($this->id && $id == $this->id) {
                        throw ValidationException::withMessages([$field => 'Legătura ar crea un ciclu în pedigree.']);
                    }
                    if (isset($visited[$id])) {
                        continue;
                    }
                    $visited[$id] = true;
                    $node = self::find($id);
                    if ($node) {
                        foreach (['father_id', 'mother_id'] as $key) {
                            if ($node->$key) {
                                $queue[] = $node->$key;
                            }
                        }
                    }
                }
            }
            if ($this->exists && (($this->sex === 'F' && self::where('father_id', $this->id)->exists()) || ($this->sex === 'M' && self::where('mother_id', $this->id)->exists()))) {
                throw ValidationException::withMessages(['sex' => 'Sexul este incompatibil cu rolul de părinte în fișele existente.']);
            }
            $old = $fresh?->status;
            $this->lock_version = ($fresh?->lock_version ?? 0) + 1;
            $saved = parent::save($options);
            if ($saved && $old !== $this->status) {
                DB::table('status_changes')->insert(['pigeon_id' => $this->id, 'from_status' => $old, 'to_status' => $this->status, 'user_id' => auth()->id(), 'created_at' => now()]);
            }
            $this->expectedVersion = null;

            return $saved;
        });
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw ValidationException::withMessages(['ring' => 'Folosește arhivarea pentru a păstra pedigree-ul.']));
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('registry_locks', function (Blueprint $t) {
            $t->id();
        });
        DB::table('registry_locks')->insert(['id' => 1]);
        foreach (['categories', 'compartments'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->timestamps();
            });
        }
        Schema::create('pigeons', function (Blueprint $t) {
            $t->id();
            $t->string('ring')->nullable();
            $t->string('ring_key')->nullable()->unique();
            $t->string('name')->nullable();
            $t->string('country', 10)->nullable();
            $t->unsignedSmallInteger('birth_year')->nullable();
            $t->string('sex', 1)->default('U');
            $t->string('color')->nullable();
            $t->string('origin')->nullable();
            $t->boolean('is_owned')->default(true);
            $t->string('status')->default('in_loft');
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('compartment_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('father_id')->nullable()->constrained('pigeons')->restrictOnDelete();
            $t->foreignId('mother_id')->nullable()->constrained('pigeons')->restrictOnDelete();
            $t->text('notes')->nullable();
            $t->text('results')->nullable();
            $t->json('photos')->nullable();
            $t->json('documents')->nullable();
            $t->boolean('archived')->default(false);
            $t->unsignedInteger('lock_version')->default(1);
            $t->timestamps();
        });
        Schema::create('status_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pigeon_id')->constrained()->restrictOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('loft_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name')->default('Team Vicov');
            $t->string('logo')->nullable();
            $t->text('pedigree_header')->nullable();
            $t->timestamps();
        });
        DB::table('loft_settings')->insert(['id' => 1, 'name' => 'Team Vicov']);
    }

    public function down(): void
    {
        foreach (['status_changes', 'pigeons', 'categories', 'compartments', 'loft_settings', 'registry_locks'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};

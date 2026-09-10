<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('location');
            $table->integer('price');
            $table->integer('available_rooms')->default(0);
            $table->text('description')->nullable();
            $table->timestampsTz();

            $table->index('price');
            $table->rawIndex('lower(name)', 'kosts_name_lower_index');
            $table->rawIndex('lower(location)', 'kosts_location_lower_index');
        });

        DB::statement('ALTER TABLE kosts ADD CONSTRAINT chk_kosts_price_positive CHECK (price >= 0)');
        DB::statement('ALTER TABLE kosts ADD CONSTRAINT chk_kosts_rooms_non_negative CHECK (available_rooms >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kosts');
    }
};

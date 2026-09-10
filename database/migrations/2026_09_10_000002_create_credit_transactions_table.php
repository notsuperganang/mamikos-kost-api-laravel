<?php

use App\Enums\CreditTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('type', 32);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'type', 'created_at']);
        });

        $types = implode(', ', array_map(fn (CreditTransactionType $t) => "'{$t->value}'", CreditTransactionType::cases()));
        DB::statement("ALTER TABLE credit_transactions ADD CONSTRAINT chk_credit_transactions_type CHECK (type IN ($types))");
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};

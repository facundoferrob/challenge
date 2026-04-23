<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('unit')->nullable();
            $table->string('type')->default('vat');
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('amount', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'country_id', 'unit', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_taxes');
    }
};

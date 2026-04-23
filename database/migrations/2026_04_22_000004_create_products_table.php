<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('reference');
            $table->string('ean')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->json('dimensions')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'reference']);
            $table->index('brand_id');
            $table->index('ean');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

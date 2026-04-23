<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Fallback VAT rate for this supplier. Used by consumers of the
            // catalog when `product_taxes` has no record for a given
            // (product, country) pair — they can fall back to this rate.
            //
            // The column is nullable; an admin / seeder populates it outside
            // the import pipeline. Importers don't touch it.
            $table->decimal('default_tax_rate', 8, 4)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('default_tax_rate');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPricingToProductsTable extends Migration
{
    public function up()
    {
        // Idempotent: a previous run of this migration may have already added
        // the price columns before failing on the lot ALTER (which required
        // doctrine/dbal). Re-running must not duplicate-add.
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'designation')) {
                $table->string('designation')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('products', 'sales_unit')) {
                $table->string('sales_unit', 8)->nullable()->after('designation');
            }
            if (!Schema::hasColumn('products', 'gross_price')) {
                $table->decimal('gross_price', 12, 4)->nullable()->after('cartQuantity');
            }
            if (!Schema::hasColumn('products', 'discount_1')) {
                $table->decimal('discount_1', 12, 4)->nullable()->after('gross_price');
            }
            if (!Schema::hasColumn('products', 'discount_2')) {
                $table->decimal('discount_2', 12, 4)->nullable()->after('discount_1');
            }
            if (!Schema::hasColumn('products', 'discount_3')) {
                $table->decimal('discount_3', 12, 4)->nullable()->after('discount_2');
            }
            if (!Schema::hasColumn('products', 'line_total_ht')) {
                $table->decimal('line_total_ht', 12, 2)->nullable()->after('discount_3');
            }
            if (!Schema::hasColumn('products', 'line_total_ttc')) {
                $table->decimal('line_total_ttc', 12, 2)->nullable()->after('line_total_ht');
            }
        });

        // The lot column was NOT NULL in the original migration; distributor
        // lines don't carry batch tracking, so allow null going forward.
        // Raw SQL avoids the doctrine/dbal dep that ->change() requires.
        DB::statement('ALTER TABLE `products` MODIFY `lot` VARCHAR(255) NULL');
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'designation', 'sales_unit',
                'gross_price', 'discount_1', 'discount_2', 'discount_3',
                'line_total_ht', 'line_total_ttc',
            ]);
        });
    }
}

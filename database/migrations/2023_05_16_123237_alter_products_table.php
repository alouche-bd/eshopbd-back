<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterProductsTable extends Migration
{
    /**
     * The original create_products_table migration already declares the
     * `lot` column, but this migration historically added it again. On
     * fresh installs that double-add fails with "Duplicate column name".
     *
     * Guard with hasColumn() so the migration is a no-op when `lot`
     * already exists (fresh DB) and still adds it on the rare legacy DB
     * where the column is missing.
     */
    public function up()
    {
        if (!Schema::hasColumn('products', 'lot')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('lot');
            });
        }
    }

    public function down()
    {
        //
    }
}

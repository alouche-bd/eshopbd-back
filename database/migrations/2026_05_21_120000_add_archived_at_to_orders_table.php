<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-archive flag for distributor orders. ADV_INTER staff can park an
 * order they don't want to forward to Sage X3 right away without losing
 * its history — archived orders are hidden from the "À traiter" view but
 * remain queryable from a dedicated "Archivées" filter.
 *
 * Nullable timestamp matches Laravel's softDelete idiom semantically
 * without coupling to the trait (which would also alter delete behaviour
 * everywhere the model is used).
 */
class AddArchivedAtToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('export_error');
            $table->index('archived_at');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
}

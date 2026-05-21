<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDistributorFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // ESHOP-{id}-{clientCode} — Sage CUSORDREF
            $table->string('customer_reference')->nullable()->after('id');
            // web | lab | lda | distributor — drives downstream workflow
            $table->string('order_type', 32)->default('web')->after('user_id');
            $table->string('client_code')->nullable()->after('order_type');
            $table->string('raison_sociale')->nullable()->after('client_code');
            $table->string('currency', 3)->nullable()->after('shippingAddress');
            $table->string('carrier_code')->nullable()->after('currency');
            $table->string('billing_country_code', 2)->nullable()->after('carrier_code');
            $table->json('billing_address')->nullable()->after('billing_country_code');
            $table->json('delivery_address')->nullable()->after('billing_address');
            $table->decimal('total_ht', 12, 2)->nullable()->after('delivery_address');
            $table->decimal('total_ttc', 12, 2)->nullable()->after('total_ht');
            $table->decimal('discount_amount', 12, 2)->nullable()->after('total_ttc');
            // pending | exported | sent | failed
            $table->string('export_status', 16)->default('pending')->after('discount_amount');
            $table->timestamp('exported_at')->nullable()->after('export_status');
            $table->timestamp('sent_at')->nullable()->after('exported_at');
            $table->string('sage_order_reference')->nullable()->after('sent_at');
            $table->string('excel_filename')->nullable()->after('sage_order_reference');
            $table->text('export_error')->nullable()->after('excel_filename');

            $table->unique('customer_reference');
            $table->index('order_type');
            $table->index('export_status');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['customer_reference']);
            $table->dropIndex(['order_type']);
            $table->dropIndex(['export_status']);
            $table->dropColumn([
                'customer_reference', 'order_type', 'client_code', 'raison_sociale',
                'currency', 'carrier_code', 'billing_country_code',
                'billing_address', 'delivery_address',
                'total_ht', 'total_ttc', 'discount_amount',
                'export_status', 'exported_at', 'sent_at',
                'sage_order_reference', 'excel_filename', 'export_error',
            ]);
        });
    }
}

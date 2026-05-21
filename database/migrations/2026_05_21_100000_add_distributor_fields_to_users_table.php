<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDistributorFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_country_code', 2)->nullable()->after('user_type');
            $table->string('sage_client_code')->nullable()->after('billing_country_code');
            $table->string('representative_code')->nullable()->after('sage_client_code');
            $table->string('representative_name')->nullable()->after('representative_code');
            $table->string('currency', 3)->nullable()->after('representative_name');
            $table->json('sage_facturation_address')->nullable()->after('currency');
            $table->json('sage_livraison_address')->nullable()->after('sage_facturation_address');
            $table->timestamp('sage_synced_at')->nullable()->after('sage_livraison_address');

            $table->index('billing_country_code');
            $table->index('sage_client_code');
            $table->index('user_type');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['billing_country_code']);
            $table->dropIndex(['sage_client_code']);
            $table->dropIndex(['user_type']);
            $table->dropColumn([
                'billing_country_code',
                'sage_client_code',
                'representative_code',
                'representative_name',
                'currency',
                'sage_facturation_address',
                'sage_livraison_address',
                'sage_synced_at',
            ]);
        });
    }
}

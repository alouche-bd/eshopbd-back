<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string("raisonsociale")->nullable();
            $table->string("codeclientGC")->nullable();
            $table->string("codeclientCPTA")->nullable();
            $table->string("sfuid")->nullable();
            $table->string("suid")->nullable();
            $table->string('email')->unique()->notNullable();
            $table->string('forgot_code')->nullable();
            $table->dateTime('forgot_time')->nullable();
            $table->string("password");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
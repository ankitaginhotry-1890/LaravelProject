<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVtigerContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vTigerContacts', function (Blueprint $table) {
            $table->longText('records')->nullable(); // longtext with utf8mb4_bin
            $table->integer('counter')->default(0); // Default value 0
            $table->timestamps(); // created_at and updated_at
            $table->integer('record_fetched')->default(0); // Default value 0
            $table->string('type', 999)->charset('utf8mb4')->collation('utf8mb4_unicode_ci'); // varchar(999) with utf8mb4_unicode_ci
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vtigerContacts');
    }
}

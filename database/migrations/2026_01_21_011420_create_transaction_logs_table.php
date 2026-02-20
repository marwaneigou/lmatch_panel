<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_type')->nullable(); // user, active_code, etc.
            $table->string('action'); // create, update, delete, renew, reset, add_credit, recover_credit
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('old_balance', 10, 2)->nullable();
            $table->decimal('new_balance', 10, 2)->nullable();
            $table->longText('details')->nullable();
            $table->string('ip')->nullable();
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
        Schema::dropIfExists('transaction_logs');
    }
}

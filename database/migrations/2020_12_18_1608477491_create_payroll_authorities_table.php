<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayrollAuthoritiesTable extends Migration
{
    public function up()
    {
        Schema::create('payroll_authorities', function (Blueprint $table) {

		$table->increments('id');
		$table->integer('company_id')->unsigned();
		$table->char('uuid',36);
		$table->string('authority_name');
		$table->enum('payment_mode',['paystack','flutterwave']);
		$table->json('default_payment_details');
		$table->json('payment_details');
		$table->tinyInteger('isActive');
		$table->timestamps();
		$table->foreign('company_id')->references('id')->on('companies');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_authorities');
    }
}
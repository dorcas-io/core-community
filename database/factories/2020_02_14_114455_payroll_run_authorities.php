<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PayrollRunAuthorities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        Schema::create('payroll_run_authorities', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('authority_id',false);
            $table->unsignedInteger('paygroup_id',false);
            $table->unsignedInteger('run_id',false)->nullable();
            $table->string('amount');
            $table->foreign('run_id')->references('id')->on('payroll_runs')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('paygroup_id')->references('id')->on('payroll_paygroup')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('authority_id')->references('id')->on('payroll_authorities')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('payroll_run_authorities');
    }
}

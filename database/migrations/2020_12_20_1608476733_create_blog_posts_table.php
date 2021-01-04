<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlogPostsTable extends Migration
{
    public function up()
    {
        Schema::create('blog_posts', function (Blueprint $table) {

		$table->bigIncrements('id')->unsigned();
		$table->char('uuid',36);
		$table->char('slug',80);
		$table->integer('company_id',)->unsigned();
		$table->bigInteger('media_id',)->unsigned()->nullable()->default('NULL');
		$table->integer('poster_id',)->unsigned();
		$table->char('poster_type',60);
		$table->char('title',80);
		$table->string('summary',1024)->nullable()->default('NULL');
		;
		$table->tinyInteger('is_published',1)->default('1');
		$table->timestamp('publish_at')->nullable()->default('NULL');
		$table->timestamp('featured_at')->nullable()->default('NULL');
		$table->timestamp('created_at')->nullable()->default('NULL');
		$table->timestamp('updated_at')->nullable()->default('NULL');
		$table->primary('id');
		$table->foreign('company_id')->references('id')->on('companies');		$table->foreign('media_id')->references('id')->on('blog_media');
        });
    }

    public function down()
    {
        Schema::dropIfExists('blog_posts');
    }
}
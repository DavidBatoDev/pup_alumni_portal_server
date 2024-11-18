<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThreadTagsTable extends Migration
{
    public function up()
    {
        Schema::create('thread_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('thread_tag_id')->autoIncrement(); // Renamed primary key
            $table->unsignedBigInteger('thread_id');
            $table->foreign('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');
            $table->unsignedBigInteger('tag_id');
            $table->foreign('tag_id')->references('tag_id')->on('tags')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('thread_tags');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('quick_survey_responses', function (Blueprint $table) {
            $table->id('quick_survey_responses_id');
            $table->unsignedBigInteger('alumni_id'); // Use alumni_id instead of user_id
            $table->json('selected_options'); // Store multiple checkboxes as JSON
            $table->string('other_response')->nullable(); // Store "Others" input text
            $table->timestamps();
    
            $table->foreign('alumni_id')->references('alumni_id')->on('alumni')->onDelete('cascade');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quick_survey_responses');
    }
    
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('follow_up_meeting', function (Blueprint $table) {
            $table->id(); // ✅ Only once
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('meeting_id');
            $table->string('meeting_agenda');
            $table->dateTime('datetime');
            $table->string('location');
            $table->timestamps();
        
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_up_meeting');
    }
};

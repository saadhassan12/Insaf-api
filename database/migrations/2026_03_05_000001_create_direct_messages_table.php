<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->onDelete('cascade'); // guest request
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade'); // who sent
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade'); // who receives
            $table->enum('message_type', ['text', 'image', 'voice', 'file'])->default('text');
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_messages');
    }
};

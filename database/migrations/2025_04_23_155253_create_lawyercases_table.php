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
        Schema::create('lawyercases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number');
            $table->string('party_a');
            $table->string('party_b');
            $table->string('case_type');
            $table->text('description')->nullable();
            $table->string('case_location');
            $table->date('institution_date');
            $table->string('task_to_be_done')->nullable();
            $table->string('court_name');
            $table->string('judge_name')->nullable();
            $table->string('client_name');
            $table->string('client_phone');
            $table->decimal('client_payment_amount', 10, 2)->nullable();
            $table->string('client_reference_of')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lawyercases');
    }
};

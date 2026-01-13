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
        Schema::table('users', function (Blueprint $table) {

            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('license_no')->nullable(); // for lawyers only
            $table->decimal('consultation_fee', 8, 2)->nullable(); // optional: adjust precision
            $table->text('lawyer_practice')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'state',
                'city',
                'license_no',
                'consultation_fee',
                'lawyer_practice'
            ]);
        });
    }
};

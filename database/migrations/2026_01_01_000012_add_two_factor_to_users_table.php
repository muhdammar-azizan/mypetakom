<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();            // encrypted by the model
            $table->text('two_factor_recovery_codes')->nullable();    // list of hashes, never the codes themselves
            $table->timestamp('two_factor_confirmed_at')->nullable(); // set once the user proved their app works
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable(); // stops one code being used twice
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_used_step']);
        });
    }
};

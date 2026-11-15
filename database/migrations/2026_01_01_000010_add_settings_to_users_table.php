<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->boolean('show_on_leaderboard')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_in_app', 'notify_email', 'show_on_leaderboard']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('programme')->nullable()->after('phone');
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('alt_email')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['programme', 'emergency_name', 'emergency_phone', 'address', 'alt_email', 'avatar_path', 'last_login_at']);
        });
    }
};

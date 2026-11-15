<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_slots', function (Blueprint $table) {
            // Off for slots that already exist, so their printed QR codes keep working.
            $table->boolean('rotating_qr')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_slots', function (Blueprint $table) {
            $table->dropColumn('rotating_qr');
        });
    }
};

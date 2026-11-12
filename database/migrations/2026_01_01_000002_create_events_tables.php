<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('level', 20); // international | national | state | district | umpsa
            $table->string('venue');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('academic_year', 9);   // e.g. 2025/2026
            $table->unsignedTinyInteger('semester'); // 1 | 2 | 3
            $table->string('status', 20)->default('active'); // active | postponed | cancelled | completed
            $table->string('approval_letter_path');
            $table->string('qr_token', 64)->unique();
            $table->timestamps();
        });

        Schema::create('event_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('position', 20); // main_committee | committee
            $table->string('position_title')->nullable(); // e.g. Director, Secretary
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('merit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_applications');
        Schema::dropIfExists('event_committees');
        Schema::dropIfExists('events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // main_committee | committee | participant
            $table->text('reason')->nullable();
            $table->string('letter_path');
            $table->string('status', 20)->default('pending'); // pending | submitted | approved | rejected
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('merits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // main_committee | committee | participant
            $table->unsignedSmallInteger('points');
            $table->string('academic_year', 9);
            $table->unsignedTinyInteger('semester');
            $table->string('source', 20); // committee | attendance | claim
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merits');
        Schema::dropIfExists('merit_claims');
    }
};

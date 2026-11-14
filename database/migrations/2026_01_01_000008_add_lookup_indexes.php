<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for columns that lists, dashboards and merit sync filter or join on. */
return new class extends Migration
{
    private const INDEXES = [
        'events' => ['advisor_id', 'status', 'starts_at'],
        'event_committees' => ['user_id'],
        'memberships' => ['status'],
        'merit_applications' => ['status'],
        'attendance_slots' => ['event_id'],
        'attendances' => ['user_id'],
        'merit_claims' => ['status', 'event_id'],
        'merits' => ['event_id', 'academic_year'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns, $table) {
                foreach ($columns as $column) {
                    $t->dropIndex("{$table}_{$column}_index");
                }
            });
        }
    }
};

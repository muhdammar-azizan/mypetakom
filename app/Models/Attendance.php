<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['attendance_slot_id', 'user_id', 'latitude', 'longitude', 'distance_m', 'checked_in_at'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AttendanceSlot::class, 'attendance_slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

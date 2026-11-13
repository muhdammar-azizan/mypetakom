<?php

namespace Database\Factories;

use App\Models\AttendanceSlot;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSlot>
 */
class AttendanceSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => 'Morning session',
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addHour(),
            'radius_m' => 100,
        ];
    }
}

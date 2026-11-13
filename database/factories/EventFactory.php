<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'advisor_id' => User::factory()->advisor(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(12),
            'level' => fake()->randomElement(array_keys(Event::LEVELS)),
            'venue' => 'Dewan Utama, UMPSA Pekan',
            'latitude' => 3.5430000,
            'longitude' => 103.4290000,
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+3 hours'),
            'academic_year' => Event::academicYearFor($start),
            'semester' => 1,
            'status' => 'active',
            'approval_letter_path' => 'approval-letters/sample.png',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

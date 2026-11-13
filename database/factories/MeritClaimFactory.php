<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MeritClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeritClaim>
 */
class MeritClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'role' => 'committee',
            'reason' => 'I helped with registration but was not listed.',
            'letter_path' => 'merit-claim-letters/sample.png',
            'status' => MeritClaim::DRAFT,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

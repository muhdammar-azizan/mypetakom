<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_card_path' => 'student-cards/sample.png',
            'status' => Membership::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => Membership::APPROVED, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => Membership::REJECTED, 'remarks' => 'Card is not clear.', 'reviewed_at' => now()]);
    }
}

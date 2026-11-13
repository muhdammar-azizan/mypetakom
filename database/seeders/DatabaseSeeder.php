<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts (password for all: "password").
     */
    public function run(): void
    {
        User::factory()->coordinator()->create([
            'name' => 'Petakom Coordinator',
            'email' => 'coordinator@petakom.test',
            'staff_id' => 'S0001',
            'programme' => 'Faculty of Computing',
        ]);

        User::factory()->advisor()->create([
            'name' => 'Event Advisor',
            'email' => 'advisor@petakom.test',
            'staff_id' => 'S0002',
            'programme' => 'BCS (Software Engineering)',
        ]);

        User::factory()->create([
            'name' => 'Demo Student',
            'email' => 'student@petakom.test',
            'student_id' => 'CB00001',
        ]);
    }
}

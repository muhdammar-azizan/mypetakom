<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo accounts (password for all: "password").
     */
    public function run(): void
    {
        $coordinator = User::factory()->coordinator()->create([
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

        // Demo student has no membership yet, so the application flow can be tried.
        User::factory()->create([
            'name' => 'Demo Student',
            'email' => 'student@petakom.test',
            'student_id' => 'CB00001',
        ]);

        $this->seedMemberships($coordinator);
    }

    private function seedMemberships(User $coordinator): void
    {
        $card = $this->sampleCard();
        $programmes = ['BCS (Software Engineering)', 'BCS (Computer Systems)', 'BCS (Graphics & Multimedia)', 'BCS (Cyber Security)'];

        foreach (['approved' => 8, 'pending' => 4, 'rejected' => 2] as $status => $count) {
            User::factory()->count($count)->create()->each(function (User $student) use ($status, $card, $programmes, $coordinator) {
                $student->update(['programme' => fake()->randomElement($programmes)]);

                Membership::factory()->create([
                    'user_id' => $student->id,
                    'student_card_path' => $card,
                    'status' => $status,
                    'remarks' => $status === 'rejected' ? 'Student card is not clear.' : null,
                    'reviewed_by' => $status === 'pending' ? null : $coordinator->id,
                    'reviewed_at' => $status === 'pending' ? null : now(),
                ]);
            });
        }
    }

    /** Generate a placeholder student card image for demo data. */
    private function sampleCard(): string
    {
        $path = 'student-cards/sample-card.png';

        if (! Storage::exists($path) && function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(480, 300);
            imagefill($img, 0, 0, imagecolorallocate($img, 31, 70, 144));
            imagefilledrectangle($img, 20, 20, 460, 280, imagecolorallocate($img, 255, 255, 255));
            imagestring($img, 5, 40, 40, 'UMPSA STUDENT CARD (DEMO)', imagecolorallocate($img, 31, 70, 144));

            ob_start();
            imagepng($img);
            Storage::put($path, ob_get_clean());
        }

        return $path;
    }
}

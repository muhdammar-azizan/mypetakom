<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Event;
use App\Models\Membership;
use App\Models\MeritApplication;
use App\Models\MeritClaim;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\MeritService;
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

        $advisor = User::factory()->advisor()->create([
            'name' => 'Event Advisor',
            'email' => 'advisor@petakom.test',
            'staff_id' => 'S0002',
            'programme' => 'BCS (Software Engineering)',
        ]);

        // Demo student has no membership yet, so the application flow can be tried.
        $demoStudent = User::factory()->create([
            'name' => 'Demo Student',
            'email' => 'student@petakom.test',
            'student_id' => 'CB00001',
        ]);

        $this->seedMemberships($coordinator);
        $this->seedEvents($advisor, $coordinator);
        $this->seedClaims($advisor);

        // A few notifications so the bell is not empty in the demo.
        Notice::send($coordinator, 'New membership application', 'A student submitted a student card for review.', route('members.index'));
        Notice::send($advisor, 'New merit claim', 'A student claimed merit for an event you advise.', route('merit-claims.index'));
        Notice::send($demoStudent, 'Welcome to MyPetakom', 'Apply for membership to join events and earn merit.', route('membership.index'));
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

    private function seedEvents(User $advisor, User $coordinator): void
    {
        $letter = $this->sampleCard();
        $members = User::whereHas('membership', fn ($q) => $q->where('status', Membership::APPROVED))->get();

        $events = [
            ['Petakom Code Sprint', 'umpsa', 'active', '-2 months', 3],
            ['National Hackathon 2026', 'national', 'completed', '-1 month', 4],
            ['State IT Carnival', 'state', 'postponed', '+3 weeks', 2],
            ['Cyber Security Talk', 'district', 'cancelled', '+1 week', 2],
            ['International AI Summit', 'international', 'active', '+2 months', 5],
        ];

        foreach ($events as [$title, $level, $status, $when, $size]) {
            $start = now()->modify($when)->setTime(9, 0);

            $event = Event::factory()->status($status)->create([
                'advisor_id' => $advisor->id,
                'title' => $title,
                'level' => $level,
                'starts_at' => $start,
                'ends_at' => (clone $start)->modify('+6 hours'),
                'academic_year' => Event::academicYearFor($start),
                'approval_letter_path' => $letter,
            ]);

            $members->shuffle()->take($size)->values()->each(fn (User $m, int $i) => $event->committees()->create([
                'user_id' => $m->id,
                'position' => $i === 0 ? 'main_committee' : 'committee',
                'position_title' => $i === 0 ? 'Director' : null,
            ]));

            // Demo attendance for past events.
            if (in_array($title, ['Petakom Code Sprint', 'National Hackathon 2026'], true)) {
                $slot = AttendanceSlot::create([
                    'event_id' => $event->id,
                    'title' => 'Main session',
                    'opens_at' => $start,
                    'closes_at' => (clone $start)->modify('+2 hours'),
                    'radius_m' => 100,
                ]);

                $members->shuffle()->take(rand(5, 8))->each(fn (User $m) => Attendance::create([
                    'attendance_slot_id' => $slot->id,
                    'user_id' => $m->id,
                    'latitude' => $event->latitude,
                    'longitude' => $event->longitude,
                    'distance_m' => rand(5, 80),
                    'checked_in_at' => (clone $start)->modify('+'.rand(5, 90).' minutes'),
                ]));
            }

            // Demo merit applications in different states.
            $state = ['Petakom Code Sprint' => 'pending', 'National Hackathon 2026' => 'approved', 'International AI Summit' => 'rejected'][$title] ?? null;

            if ($state) {
                MeritApplication::create([
                    'event_id' => $event->id,
                    'status' => $state,
                    'remarks' => $state === 'rejected' ? 'Approval letter is missing the signature.' : null,
                    'reviewed_by' => $state === 'pending' ? null : $coordinator->id,
                    'reviewed_at' => $state === 'pending' ? null : now(),
                ]);

                MeritService::sync($event);
            }
        }
    }

    /** A few claims in different states, for the demo advisor to review. */
    private function seedClaims(User $advisor): void
    {
        $letter = $this->sampleCard();
        $event = Event::where('advisor_id', $advisor->id)->where('title', 'National Hackathon 2026')->first();
        $members = User::whereHas('membership', fn ($q) => $q->where('status', Membership::APPROVED))
            ->whereDoesntHave('merits', fn ($q) => $q->where('event_id', $event->id))->get();

        // Always have four claimants, even when most members already hold merit for this event.
        $members = $members->values();
        while ($members->count() < 4) {
            $extra = User::factory()->create();
            Membership::factory()->approved()->for($extra)->create(['student_card_path' => $this->sampleCard()]);
            $members->push($extra);
        }

        foreach (['pending', 'submitted', 'submitted', 'rejected'] as $i => $status) {
            MeritClaim::create([
                'user_id' => $members[$i]->id,
                'event_id' => $event->id,
                'role' => 'participant',
                'reason' => 'I attended the hackathon but my check-in was not recorded.',
                'letter_path' => $letter,
                'status' => $status,
                'remarks' => $status === 'rejected' ? 'The attached letter is unreadable.' : null,
                'reviewed_by' => $status === 'rejected' ? $advisor->id : null,
                'reviewed_at' => $status === 'rejected' ? now() : null,
            ]);
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

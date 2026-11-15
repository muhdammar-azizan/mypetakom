<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Merit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name, int $points, array $attrs = [], string $year = '2025/2026', int $semester = 1): User
    {
        $student = User::factory()->create(['name' => $name, 'programme' => $name.' Programme'] + $attrs);
        $this->merit($student, $points, $year, $semester);

        return $student;
    }

    private function merit(User $student, int $points, string $year = '2025/2026', int $semester = 1): void
    {
        Merit::create([
            'user_id' => $student->id, 'event_id' => Event::factory()->create()->id, 'role' => 'participant',
            'points' => $points, 'academic_year' => $year, 'semester' => $semester, 'source' => 'attendance',
        ]);
    }

    private function rows(User $viewer, string $query = ''): array
    {
        $response = $this->actingAs($viewer)->get('/leaderboard'.$query)->assertOk();

        return [
            'top' => $response->viewData('top')->all(),
            'mine' => $response->viewData('mine'),
            'total' => $response->viewData('total'),
            'response' => $response,
        ];
    }

    // ---- Access ----

    public function test_every_signed_in_role_can_open_it_and_guests_cannot(): void
    {
        $this->get('/leaderboard')->assertRedirect('/login');

        foreach ([User::factory()->create(), User::factory()->advisor()->create(), User::factory()->coordinator()->create()] as $user) {
            $this->actingAs($user)->get('/leaderboard')->assertOk()->assertSee('Leaderboard');
        }
    }

    public function test_it_is_in_every_role_menu(): void
    {
        foreach ([User::factory()->create(), User::factory()->advisor()->create(), User::factory()->coordinator()->create()] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertSee(route('leaderboard.index'), false);
        }
    }

    // ---- Ranking ----

    public function test_students_are_ranked_by_total_points_and_ties_share_a_rank(): void
    {
        $this->student('Alya', 100);
        $this->student('Budi', 70);
        $this->student('Citra', 70);
        $this->student('Dewi', 20);

        $result = $this->rows(User::factory()->advisor()->create());

        $this->assertSame(['Alya', 'Budi', 'Citra', 'Dewi'], array_column($result['top'], 'name'));
        $this->assertSame([1, 2, 2, 4], array_column($result['top'], 'rank'));
        $this->assertSame(4, $result['total']);
    }

    public function test_points_from_several_events_are_added_up(): void
    {
        $student = $this->student('Alya', 30);
        $this->merit($student, 25);
        $this->student('Budi', 50);

        $top = $this->rows($student)['top'];

        $this->assertSame('Alya', $top[0]['name']);
        $this->assertSame(55, $top[0]['points']);
        $this->assertSame(2, $top[0]['events']);
    }

    public function test_only_students_with_merit_are_listed(): void
    {
        $this->student('Has Merit', 10);
        User::factory()->create(['name' => 'No Merit Yet']);
        User::factory()->advisor()->create(['name' => 'Staff Person']);

        $response = $this->rows(User::factory()->create())['response'];

        $response->assertSee('Has Merit')->assertDontSee('No Merit Yet')->assertDontSee('Staff Person');
    }

    public function test_it_filters_by_academic_year_and_semester(): void
    {
        $a = $this->student('Alya', 90, [], '2024/2025', 1);
        $this->merit($a, 5, '2025/2026', 2);
        $this->student('Budi', 40, [], '2025/2026', 1);
        $viewer = User::factory()->advisor()->create();

        $all = $this->rows($viewer)['top'];
        $this->assertSame(['Alya', 'Budi'], array_column($all, 'name'));

        $year = $this->rows($viewer, '?year=2025%2F2026')['top'];
        $this->assertSame(['Budi', 'Alya'], array_column($year, 'name'));
        $this->assertSame([40, 5], array_column($year, 'points'));

        $sem = $this->rows($viewer, '?year=2025%2F2026&semester=2')['top'];
        $this->assertSame(['Alya'], array_column($sem, 'name'));
    }

    public function test_bad_filters_are_rejected(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get('/leaderboard?year=not-a-year')->assertSessionHasErrors('year');
        $this->actingAs($viewer)->get('/leaderboard?semester=9')->assertSessionHasErrors('semester');
    }

    public function test_empty_period_says_so(): void
    {
        $this->actingAs(User::factory()->create())->get('/leaderboard')->assertSee('No merit has been recorded for this period yet.');
    }

    public function test_only_the_top_twenty_rows_are_shown(): void
    {
        foreach (range(1, 25) as $i) {
            $this->student("Student {$i}", 1000 - $i);
        }

        $result = $this->rows(User::factory()->advisor()->create());

        $this->assertCount(20, $result['top']);
        $this->assertSame(25, $result['total']);
    }

    // ---- Your own position ----

    public function test_a_student_sees_their_own_rank_card(): void
    {
        $this->student('Alya', 100);
        $me = $this->student('Me Myself', 60);

        $result = $this->rows($me);

        $this->assertSame(2, $result['mine']['rank']);
        $result['response']->assertSee('Your rank')->assertSee('#2')->assertSee('(you)');
    }

    public function test_a_student_ranked_below_the_table_still_sees_their_row(): void
    {
        foreach (range(1, 25) as $i) {
            $this->student("Top {$i}", 1000 - $i);
        }
        $me = $this->student('Low Scorer', 3);

        $result = $this->rows($me);

        $this->assertCount(20, $result['top']);
        $this->assertSame(26, $result['mine']['rank']);
        $result['response']->assertSee('Low Scorer')->assertSee('#26');
    }

    public function test_a_student_without_merit_is_told_they_are_not_ranked(): void
    {
        $this->student('Alya', 10);

        $this->actingAs(User::factory()->create())->get('/leaderboard')->assertSee('you are not ranked');
    }

    public function test_staff_do_not_get_the_personal_rank_card(): void
    {
        $this->student('Alya', 10);

        $this->actingAs(User::factory()->advisor()->create())->get('/leaderboard')->assertDontSee('Your rank');
    }

    // ---- Privacy ----

    public function test_a_hidden_student_keeps_their_rank_but_shows_as_anonymous_to_students_and_advisors(): void
    {
        $this->student('Alya', 100);
        $this->student('Secret Sam', 80, ['show_on_leaderboard' => false]);
        $this->student('Budi', 60);

        foreach ([User::factory()->create(), User::factory()->advisor()->create()] as $viewer) {
            $result = $this->rows($viewer);

            $this->assertSame(['Alya', 'Anonymous student', 'Budi'], array_column($result['top'], 'name'));
            $this->assertSame([1, 2, 3], array_column($result['top'], 'rank'));
            $this->assertNull($result['top'][1]['programme']);
            $result['response']->assertDontSee('Secret Sam');
        }
    }

    public function test_a_hidden_student_sees_their_own_name_and_a_note(): void
    {
        $sam = $this->student('Secret Sam', 80, ['show_on_leaderboard' => false]);

        $result = $this->rows($sam);

        $this->assertSame('Secret Sam', $result['top'][0]['name']);
        $result['response']->assertSee('Hidden from other students');
    }

    public function test_the_coordinator_sees_real_names_with_a_hidden_note(): void
    {
        $this->student('Secret Sam', 80, ['show_on_leaderboard' => false]);

        $result = $this->rows(User::factory()->coordinator()->create());

        $this->assertSame('Secret Sam', $result['top'][0]['name']);
        $result['response']->assertSee('Hidden from other students');
    }

    public function test_student_ids_and_emails_are_never_shown(): void
    {
        $student = $this->student('Alya', 100, ['student_id' => 'CB99999', 'email' => 'alya.private@example.test']);

        $html = $this->actingAs(User::factory()->create())->get('/leaderboard')->getContent();

        $this->assertStringNotContainsString('CB99999', $html);
        $this->assertStringNotContainsString('alya.private@example.test', $html);
        $this->assertNotNull($student);
    }

    public function test_names_are_escaped(): void
    {
        $this->student('<b>Bold</b> Hacker', 10);

        $html = $this->actingAs(User::factory()->create())->get('/leaderboard')->getContent();

        $this->assertStringNotContainsString('<b>Bold</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }

    public function test_turning_the_setting_off_hides_the_name_on_the_board(): void
    {
        $me = $this->student('Visible Vera', 50);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get('/leaderboard')->assertSee('Visible Vera');

        $this->actingAs($me)->patch('/settings/preferences', ['notify_in_app' => '1'])->assertRedirect();

        $this->actingAs($viewer)->get('/leaderboard')->assertDontSee('Visible Vera')->assertSee('Anonymous student');
    }
}

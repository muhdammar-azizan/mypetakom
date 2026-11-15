<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        return Event::factory()->create($attrs + [
            'starts_at' => now()->startOfMonth()->addDays(9)->setTime(9, 0),
            'ends_at' => now()->startOfMonth()->addDays(9)->setTime(12, 0),
        ]);
    }

    public function test_calendar_needs_login_and_opens_for_every_role(): void
    {
        $this->get('/calendar')->assertRedirect('/login');

        foreach ([User::factory()->create(), User::factory()->advisor()->create(), User::factory()->coordinator()->create()] as $user) {
            $this->actingAs($user)->get('/calendar')->assertOk()->assertSee(now()->format('F Y'));
        }
    }

    public function test_calendar_link_is_in_every_role_menu(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee(route('calendar.index'), false);
        $this->actingAs(User::factory()->advisor()->create())->get('/dashboard')->assertSee(route('calendar.index'), false);
        $this->actingAs(User::factory()->coordinator()->create())->get('/dashboard')->assertSee(route('calendar.index'), false);
    }

    public function test_coordinator_sees_all_events_and_advisor_only_their_own(): void
    {
        $mine = $this->event(['title' => 'Mine Meetup']);
        $this->event(['title' => 'Other Meetup']);

        $this->actingAs($this->userCoordinator())->get('/calendar')->assertSee('Mine Meetup')->assertSee('Other Meetup');
        $this->actingAs($mine->advisor)->get('/calendar')->assertSee('Mine Meetup')->assertDontSee('Other Meetup');
    }

    private function userCoordinator(): User
    {
        return User::factory()->coordinator()->create();
    }

    public function test_student_sees_every_event_and_gets_the_right_link_for_each(): void
    {
        $student = User::factory()->create();
        $joined = $this->event(['title' => 'Joined Camp']);
        $joined->committees()->create(['user_id' => $student->id, 'position' => 'committee']);
        $other = $this->event(['title' => 'Public Talk']);

        $response = $this->actingAs($student)->get('/calendar')->assertOk()->assertSee('Joined Camp')->assertSee('Public Talk');

        // Committee event opens the full page, everything else the public info page.
        $response->assertSee(route('events.show', $joined), false);
        $response->assertSee(route('events.public', $other->qr_token), false);
        $response->assertDontSee(route('events.show', $other), false);
        $response->assertSee('You are on the committee');
    }

    public function test_months_can_be_browsed_and_bad_values_are_rejected(): void
    {
        $user = $this->userCoordinator();
        $this->event(['title' => 'This Month Only']);

        $next = now()->addMonth()->format('Y-m');
        $this->actingAs($user)->get("/calendar?month={$next}")->assertOk()
            ->assertSee(now()->addMonth()->format('F Y'))->assertDontSee('This Month Only');

        $this->actingAs($user)->get('/calendar?month=2026-13')->assertSessionHasErrors('month');
        $this->actingAs($user)->get('/calendar?month=garbage')->assertSessionHasErrors('month');
    }

    public function test_month_navigation_links_point_to_previous_and_next(): void
    {
        $this->actingAs($this->userCoordinator())->get('/calendar?month=2026-03')->assertOk()
            ->assertSee('March 2026')
            ->assertSee('month=2026-02', false)
            ->assertSee('month=2026-04', false);
    }

    public function test_multi_day_event_appears_on_every_day_it_covers(): void
    {
        $this->event([
            'title' => 'Three Day Camp',
            'starts_at' => now()->startOfMonth()->addDays(4)->setTime(9, 0),
            'ends_at' => now()->startOfMonth()->addDays(6)->setTime(17, 0),
        ]);

        $html = $this->actingAs($this->userCoordinator())->get('/calendar')->getContent();

        // Once per covered day in the grid, plus once in the phone list.
        $this->assertSame(3, preg_match_all('/Three Day Camp\s*<\/a>/', $html));   // grid: three days
        $this->assertSame(1, substr_count($html, '>Three Day Camp</span>'));        // phone list: once
    }

    public function test_event_from_neighbouring_month_shows_in_the_grid_edge_days(): void
    {
        $month = now()->startOfMonth();
        // Last day of the previous month is normally visible in the first week of the grid.
        $edge = $month->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);

        if ($edge->lt($month)) {
            $this->event(['title' => 'Edge Event', 'starts_at' => $edge->copy()->setTime(10, 0), 'ends_at' => $edge->copy()->setTime(11, 0)]);

            $this->actingAs($this->userCoordinator())->get('/calendar')->assertSee('Edge Event');
        } else {
            $this->assertTrue(true); // month starts on a Monday: nothing from the previous month is shown
        }
    }

    public function test_cancelled_and_postponed_events_are_marked(): void
    {
        $this->event(['title' => 'Called Off', 'status' => 'cancelled']);
        $this->event(['title' => 'Moved Later', 'status' => 'postponed']);

        $this->actingAs($this->userCoordinator())->get('/calendar')->assertSee('Cancelled')->assertSee('Postponed')->assertSee('line-through');
    }

    public function test_many_events_on_one_day_collapse_behind_a_more_button(): void
    {
        foreach (range(1, 5) as $i) {
            $this->event(['title' => "Busy Day Event {$i}"]);
        }

        $this->actingAs($this->userCoordinator())->get('/calendar')->assertSee('+2 more');
    }

    public function test_empty_month_says_so(): void
    {
        $this->actingAs($this->userCoordinator())->get('/calendar')->assertSee('No events this month.');
    }

    public function test_grid_is_whole_weeks_starting_on_monday_in_date_order(): void
    {
        // November 2026 starts on a Sunday, so the grid runs Monday 26 Oct to Sunday 6 Dec: six full weeks.
        $weeks = $this->actingAs($this->userCoordinator())->get('/calendar?month=2026-11')->viewData('weeks');

        $this->assertCount(6, $weeks);
        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys($weeks));

        $expected = \Illuminate\Support\Carbon::parse('2026-10-26');
        foreach ($weeks as $week) {
            $this->assertCount(7, $week);
            $this->assertSame(1, $week[0]['date']->dayOfWeekIso);   // Monday first
            foreach ($week as $day) {
                $this->assertTrue($day['date']->isSameDay($expected));
                $expected->addDay();
            }
        }

        $this->assertFalse($weeks[0][0]['in_month']);   // 26 Oct belongs to the previous month
        $this->assertTrue($weeks[0][6]['in_month']);    // 1 Nov is in November
        $this->assertFalse($weeks[5][6]['in_month']);   // 6 Dec belongs to the next month
    }

    public function test_building_the_grid_does_not_change_event_dates(): void
    {
        $event = $this->event(['starts_at' => now()->startOfMonth()->subDays(20)->setTime(9, 0), 'ends_at' => now()->startOfMonth()->addDays(40)->setTime(9, 0)]);
        $before = [$event->starts_at->toDateTimeString(), $event->ends_at->toDateTimeString()];

        $weeks = $this->actingAs($this->userCoordinator())->get('/calendar')->viewData('weeks');

        // A long event covers every visible day exactly once and the first visible day is still the Monday of week one.
        foreach ($weeks as $week) {
            foreach ($week as $day) {
                $this->assertCount(1, $day['events']);
            }
        }
        $this->assertSame(1, $weeks[0][0]['date']->dayOfWeekIso);
        $this->assertSame($before, [$event->fresh()->starts_at->toDateTimeString(), $event->fresh()->ends_at->toDateTimeString()]);
    }
}

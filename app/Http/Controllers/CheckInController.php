<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSlot;
use App\Models\Membership;
use App\Models\User;
use App\Services\MeritService;
use App\Support\Geo;
use App\Support\RotatingCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class CheckInController extends Controller
{
    /** Page opened by scanning the slot QR. Public: the student identifies with ID and password. */
    public function show(Request $request, string $token): View
    {
        $slot = $this->slot($token);

        return view('checkin.show', [
            'slot' => $slot,
            'code' => $request->query('c'),
            // A rotating slot needs a link that was scanned a moment ago.
            'expired' => $slot->rotating_qr && ! RotatingCode::valid($slot, $request->query('c')),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $slot = $this->slot($token);
        $event = $slot->event;

        if ($slot->rotating_qr && ! RotatingCode::valid($slot, $request->input('code'), RotatingCode::SUBMIT_GRACE)) {
            return $this->fail('This QR code has expired. Please scan the code on the screen again.');
        }

        $data = $request->validate([
            'student_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Your location is required. Allow location access and try again.',
            'longitude.required' => 'Your location is required. Allow location access and try again.',
        ]);

        // Limit wrong passwords per student ID, so one account cannot be guessed from many addresses.
        $limiterKey = 'checkin-login:'.strtoupper($data['student_id']);

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return $this->fail('Too many failed attempts. Please wait '.RateLimiter::availableIn($limiterKey).' seconds and try again.');
        }

        $student = User::where('role', User::ROLE_STUDENT)->where('student_id', strtoupper($data['student_id']))->first();

        if (! $student || $student->isDeactivated() || ! Hash::check($data['password'], $student->password)) {
            RateLimiter::hit($limiterKey, 60);

            return $this->fail('Student ID or password is incorrect.');
        }

        RateLimiter::clear($limiterKey);

        if (in_array($event->status, ['cancelled', 'postponed'], true)) {
            return $this->fail("This event has been {$event->status}. Check-in is not available.");
        }

        if ($slot->window === 'upcoming') {
            return $this->fail('This attendance slot has not opened yet. It opens at '.$slot->opens_at->format('d M Y, h:i A').'.');
        }

        if ($slot->window === 'closed') {
            return $this->fail('This attendance slot has closed.');
        }

        if ($student->membership?->status !== Membership::APPROVED) {
            return $this->fail('Only approved Petakom members can check in.');
        }

        if ($slot->attendances()->where('user_id', $student->id)->exists()) {
            return $this->fail('You have already checked in to this slot.');
        }

        $distance = Geo::distanceMeters((float) $data['latitude'], (float) $data['longitude'], $event->latitude, $event->longitude);

        if ($distance > $slot->radius_m) {
            return $this->fail("You are {$distance} m from the venue. You must be within {$slot->radius_m} m to check in.");
        }

        $attendance = Attendance::create([
            'attendance_slot_id' => $slot->id,
            'user_id' => $student->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'distance_m' => $distance,
            'checked_in_at' => now(),
        ]);

        MeritService::sync($event);

        return redirect()->route('checkin.show', $token)->with('checkin', [
            'name' => $student->name,
            'student_id' => $student->student_id,
            'distance' => $attendance->distance_m,
            'time' => $attendance->checked_in_at->format('h:i A'),
        ]);
    }

    private function slot(string $token): AttendanceSlot
    {
        return AttendanceSlot::with('event.advisor')->where('qr_token', $token)->firstOrFail();
    }

    private function fail(string $message): RedirectResponse
    {
        return back()->withErrors(['checkin' => $message])->withInput(request()->only('student_id', 'latitude', 'longitude'));
    }
}

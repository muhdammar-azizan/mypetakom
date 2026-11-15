<?php

use App\Http\Controllers\AttendanceSlotController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\Coordinator\ActivityLogController;
use App\Http\Controllers\Coordinator\MemberController;
use App\Http\Controllers\Coordinator\ReportController;
use App\Http\Controllers\Coordinator\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MeritApplicationController;
use App\Http\Controllers\MeritClaimController;
use App\Http\Controllers\MeritController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

// Public event information page (target of the event QR code)
Route::get('/e/{token}', [EventController::class, 'publicShow'])->name('events.public');

// Student QR: public identity + merit page
Route::get('/qr/{token}', [MeritController::class, 'publicShow'])->name('merit.qr');

// Student check-in (public page: the student proves identity with student ID + password)
Route::get('/checkin/{token}', [CheckInController::class, 'show'])->name('checkin.show');
Route::post('/checkin/{token}', [CheckInController::class, 'store'])->middleware('throttle:20,1')->name('checkin.store');

Route::middleware('auth')->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences');
    Route::delete('/settings/account', [SettingsController::class, 'destroy'])->name('settings.account.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('/membership-cards/{membership}', [MembershipController::class, 'card'])->name('membership.card');

    // Student: membership application
    Route::middleware('role:student')->prefix('membership')->name('membership.')->group(function () {
        Route::get('/', [MembershipController::class, 'index'])->name('index');
        Route::post('/', [MembershipController::class, 'store'])->name('store');
        Route::delete('/', [MembershipController::class, 'destroy'])->name('destroy');
    });

    // Student merit + claims (Module 4)
    Route::middleware('role:student')->group(function () {
        Route::get('/merit', [MeritController::class, 'index'])->name('merit.index');
        Route::get('/merit-claims/create', [MeritClaimController::class, 'create'])->name('merit-claims.create');
        Route::post('/merit-claims', [MeritClaimController::class, 'store'])->name('merit-claims.store');
        Route::get('/merit-claims/{claim}/edit', [MeritClaimController::class, 'edit'])->name('merit-claims.edit');
        Route::put('/merit-claims/{claim}', [MeritClaimController::class, 'update'])->name('merit-claims.update');
        Route::delete('/merit-claims/{claim}', [MeritClaimController::class, 'destroy'])->name('merit-claims.destroy');
        Route::post('/merit-claims/{claim}/submit', [MeritClaimController::class, 'submit'])->name('merit-claims.submit');
    });
    Route::middleware('role:student,advisor')->get('/merit-claims', [MeritClaimController::class, 'index'])->name('merit-claims.index');
    Route::get('/merit-claims/{claim}', [MeritClaimController::class, 'show'])->name('merit-claims.show')->whereNumber('claim');
    Route::get('/merit-claims/{claim}/letter', [MeritClaimController::class, 'letter'])->name('merit-claims.letter');
    Route::middleware('role:advisor')->group(function () {
        Route::post('/merit-claims/{claim}/approve', [MeritClaimController::class, 'approve'])->name('merit-claims.approve');
        Route::post('/merit-claims/{claim}/reject', [MeritClaimController::class, 'reject'])->name('merit-claims.reject');
    });

    // Events: advisors manage their own, coordinators browse all, students see their committee roles
    Route::middleware('role:advisor,coordinator')->get('/events', [EventController::class, 'index'])->name('events.index');
    Route::middleware('role:advisor,coordinator')->get('/events/export', [EventController::class, 'export'])->name('events.export');

    Route::middleware('role:advisor')->group(function () {
        Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/committee', [EventController::class, 'addCommittee'])->name('events.committee.store');
        Route::delete('/events/{event}/committee/{member}', [EventController::class, 'removeCommittee'])->name('events.committee.destroy');

        Route::post('/events/{event}/merit-application', [MeritApplicationController::class, 'store'])->name('events.merit.store');
        Route::delete('/events/{event}/merit-application', [MeritApplicationController::class, 'destroy'])->name('events.merit.destroy');
    });

    // Attendance slots: advisors manage, coordinators get the dashboard and can view
    Route::middleware('role:advisor,coordinator')->group(function () {
        Route::get('/attendance', [AttendanceSlotController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/{slot}', [AttendanceSlotController::class, 'show'])->name('attendance.show')->whereNumber('slot');
        Route::get('/attendance/{slot}/qr', [AttendanceSlotController::class, 'qr'])->name('attendance.qr')->whereNumber('slot');
        Route::get('/attendance/{slot}/csv', [AttendanceSlotController::class, 'csv'])->name('attendance.csv')->whereNumber('slot');
    });
    Route::middleware('role:advisor')->group(function () {
        Route::get('/attendance/create', [AttendanceSlotController::class, 'create'])->name('attendance.create');
        Route::post('/attendance', [AttendanceSlotController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/{slot}/edit', [AttendanceSlotController::class, 'edit'])->name('attendance.edit');
        Route::put('/attendance/{slot}', [AttendanceSlotController::class, 'update'])->name('attendance.update');
        Route::delete('/attendance/{slot}', [AttendanceSlotController::class, 'destroy'])->name('attendance.destroy');
        Route::delete('/attendance/{slot}/records/{record}', [AttendanceSlotController::class, 'destroyRecord'])->name('attendance.records.destroy');
    });

    // Merit applications: advisors apply, coordinators decide
    Route::middleware('role:advisor,coordinator')->group(function () {
        Route::get('/merit-applications', [MeritApplicationController::class, 'index'])->name('merit-applications.index');
        Route::get('/merit-applications/{application}', [MeritApplicationController::class, 'show'])->name('merit-applications.show');
    });
    Route::middleware('role:coordinator')->group(function () {
        Route::post('/merit-applications/{application}/approve', [MeritApplicationController::class, 'approve'])->name('merit-applications.approve');
        Route::post('/merit-applications/{application}/reject', [MeritApplicationController::class, 'reject'])->name('merit-applications.reject');
    });

    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/letter', [EventController::class, 'letter'])->name('events.letter');
    Route::middleware('role:student')->get('/my-events', [EventController::class, 'mine'])->name('my-events.index');

    // Coordinator: members, user accounts, reports
    Route::middleware('role:coordinator')->group(function () {
        Route::prefix('members')->name('members.')->group(function () {
            Route::get('/export', [MemberController::class, 'export'])->name('export');
            Route::get('/', [MemberController::class, 'index'])->name('index');
            Route::get('/{membership}', [MemberController::class, 'show'])->name('show');
            Route::post('/{membership}/approve', [MemberController::class, 'approve'])->name('approve');
            Route::post('/{membership}/reject', [MemberController::class, 'reject'])->name('reject');
            Route::delete('/{membership}', [MemberController::class, 'destroy'])->name('destroy');
        });

        Route::resource('users', UserController::class)->except('show');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{slug}/csv', [ReportController::class, 'csv'])->name('reports.csv');
    });
});

require __DIR__.'/auth.php';

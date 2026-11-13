<?php

use App\Http\Controllers\AttendanceSlotController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\Coordinator\MemberController;
use App\Http\Controllers\Coordinator\ReportController;
use App\Http\Controllers\Coordinator\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MeritApplicationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

// Public event information page (target of the event QR code)
Route::get('/e/{token}', [EventController::class, 'publicShow'])->name('events.public');

// Student check-in (public page: the student proves identity with student ID + password)
Route::get('/checkin/{token}', [CheckInController::class, 'show'])->name('checkin.show');
Route::post('/checkin/{token}', [CheckInController::class, 'store'])->middleware('throttle:20,1')->name('checkin.store');

Route::middleware('auth')->group(function () {
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

    // Events: advisors manage their own, coordinators browse all, students see their committee roles
    Route::middleware('role:advisor,coordinator')->get('/events', [EventController::class, 'index'])->name('events.index');

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
            Route::get('/', [MemberController::class, 'index'])->name('index');
            Route::get('/{membership}', [MemberController::class, 'show'])->name('show');
            Route::post('/{membership}/approve', [MemberController::class, 'approve'])->name('approve');
            Route::post('/{membership}/reject', [MemberController::class, 'reject'])->name('reject');
            Route::delete('/{membership}', [MemberController::class, 'destroy'])->name('destroy');
        });

        Route::resource('users', UserController::class)->except('show');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
});

require __DIR__.'/auth.php';

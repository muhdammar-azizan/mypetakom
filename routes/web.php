<?php

use App\Http\Controllers\Coordinator\MemberController;
use App\Http\Controllers\Coordinator\ReportController;
use App\Http\Controllers\Coordinator\UserController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

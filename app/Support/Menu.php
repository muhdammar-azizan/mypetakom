<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Menu
{
    /**
     * Sidebar items for a user's role.
     * Items whose route is not built yet fall back to "#".
     *
     * @return list<array{label: string, icon: string, url: string, active: bool}>
     */
    public static function for(User $user): array
    {
        $items = match ($user->role) {
            User::ROLE_COORDINATOR => [
                ['Dashboard', 'home', 'dashboard'],
                ['Calendar', 'calendar-days', 'calendar.index'],
                ['Leaderboard', 'trophy', 'leaderboard.index'],
                ['Members', 'users', 'members.index'],
                ['User Accounts', 'settings', 'users.index'],
                ['Events', 'calendar', 'events.index'],
                ['Merit Applications', 'file', 'merit-applications.index'],
                ['Attendance', 'clipboard', 'attendance.index'],
                ['Reports', 'chart', 'reports.index'],
                ['Activity Log', 'clipboard', 'activity.index'],
            ],
            User::ROLE_ADVISOR => [
                ['Dashboard', 'home', 'dashboard'],
                ['Calendar', 'calendar-days', 'calendar.index'],
                ['Leaderboard', 'trophy', 'leaderboard.index'],
                ['My Events', 'calendar', 'events.index'],
                ['Merit Applications', 'file', 'merit-applications.index'],
                ['Attendance', 'clipboard', 'attendance.index'],
                ['Merit Claims', 'award', 'merit-claims.index'],
            ],
            default => [
                ['Dashboard', 'home', 'dashboard'],
                ['Calendar', 'calendar-days', 'calendar.index'],
                ['Leaderboard', 'trophy', 'leaderboard.index'],
                ['Membership', 'users', 'membership.index'],
                ['My Events', 'calendar', 'my-events.index'],
                ['Merit', 'award', 'merit.index'],
                ['Merit Claims', 'file', 'merit-claims.index'],
            ],
        };

        return array_map(function (array $item) {
            [$label, $icon, $route] = $item;
            $exists = Route::has($route);

            return [
                'label' => $label,
                'icon' => $icon,
                'url' => $exists ? route($route) : '#',
                'active' => $exists && request()->routeIs(str($route)->beforeLast('.').'.*', $route),
            ];
        }, $items);
    }
}

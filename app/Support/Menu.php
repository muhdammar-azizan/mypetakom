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
                ['Members', 'users', 'members.index'],
                ['Events', 'calendar', 'events.index'],
                ['Merit Applications', 'file', 'merit-applications.index'],
                ['Reports', 'chart', 'reports.index'],
            ],
            User::ROLE_ADVISOR => [
                ['Dashboard', 'home', 'dashboard'],
                ['My Events', 'calendar', 'events.index'],
                ['Attendance', 'clipboard', 'attendance.index'],
                ['Merit Claims', 'award', 'merit-claims.index'],
            ],
            default => [
                ['Dashboard', 'home', 'dashboard'],
                ['Membership', 'users', 'membership.index'],
                ['My Events', 'calendar', 'my-events.index'],
                ['Merit', 'award', 'merit.index'],
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    /** Mark one notice as read, then go to the page it points to. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notice = $request->user()->notifications()->findOrFail($id);
        $notice->markAsRead();

        $url = $notice->data['url'] ?? route('dashboard');

        return redirect($url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('toast', 'All notifications marked as read.');
    }
}

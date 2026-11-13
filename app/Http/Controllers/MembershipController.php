<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MembershipController extends Controller
{
    public function index(Request $request): View
    {
        return view('membership.index', ['membership' => $request->user()->membership]);
    }

    /** Submit a student card, or resubmit after a rejection / replace while pending. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'student_card' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $membership = $request->user()->membership;

        if ($membership?->isApproved()) {
            return back()->with('toast', 'Your membership is already approved.');
        }

        $path = $request->file('student_card')->store('student-cards');

        if ($membership) {
            Storage::delete($membership->student_card_path);
        }

        Membership::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'student_card_path' => $path,
                'status' => Membership::PENDING,
                'remarks' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        return redirect()->route('membership.index')->with('toast', 'Membership application submitted.');
    }

    /** Cancel an application that has not been approved. */
    public function destroy(Request $request): RedirectResponse
    {
        $membership = $request->user()->membership;

        abort_if(! $membership || $membership->isApproved(), 403);

        Storage::delete($membership->student_card_path);
        $membership->delete();

        return redirect()->route('membership.index')->with('toast', 'Application cancelled.');
    }

    /** Serve a student card to its owner or a coordinator. */
    public function card(Request $request, Membership $membership): BinaryFileResponse
    {
        abort_unless($request->user()->isCoordinator() || $membership->user_id === $request->user()->id, 403);
        abort_unless(Storage::exists($membership->student_card_path), 404);

        return response()->file(Storage::path($membership->student_card_path));
    }
}

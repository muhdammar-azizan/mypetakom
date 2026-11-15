<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Notifications\Notice;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $memberships = Membership::with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)
                    ->orWhere('student_id', 'like', $term)
                    ->orWhere('programme', 'like', $term));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('members.index', [
            'memberships' => $memberships,
            'counts' => Membership::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Membership $membership): View
    {
        return view('members.show', ['membership' => $membership->load('user', 'reviewer')]);
    }

    public function approve(Request $request, Membership $membership): RedirectResponse
    {
        $membership->update([
            'status' => Membership::APPROVED,
            'remarks' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Activity::log('membership.approved', 'Approved the membership of '.$membership->user->name.' ('.$membership->user->student_id.').', $membership);
        Notice::send($membership->user, 'Membership approved', 'You are now a Petakom member. You can join events and earn merit.', route('membership.index'));

        return redirect()->route('members.show', $membership)->with('toast', 'Membership approved.');
    }

    public function reject(Request $request, Membership $membership): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:500']]);

        $membership->update([
            'status' => Membership::REJECTED,
            'remarks' => $data['remarks'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Activity::log('membership.rejected', 'Rejected the membership of '.$membership->user->name.' ('.$membership->user->student_id.'): '.$data['remarks'], $membership);
        Notice::send($membership->user, 'Membership rejected', $data['remarks'], route('membership.index'));

        return redirect()->route('members.show', $membership)->with('toast', 'Membership rejected.');
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        Activity::log('membership.deleted', 'Deleted the membership record of '.$membership->user->name.' ('.$membership->user->student_id.').', $membership);
        Storage::delete($membership->student_card_path);
        $membership->delete();

        return redirect()->route('members.index')->with('toast', 'Membership record deleted.');
    }
}

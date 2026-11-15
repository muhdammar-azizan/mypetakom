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
use App\Support\Csv;
use App\Support\DateFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $memberships = $this->filtered($request)->latest()->paginate(10)->withQueryString();

        return view('members.index', [
            'memberships' => $memberships,
            'counts' => Membership::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /** The members list as a CSV file, with the same search and filters as the page. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->latest()->get()->map(fn (Membership $m) => [
            $m->user->name,
            $m->user->student_id,
            $m->user->programme,
            $m->user->email,
            ucfirst($m->status),
            $m->created_at->format('Y-m-d'),
            $m->reviewer?->name,
            $m->reviewed_at?->format('Y-m-d'),
        ]);

        return Csv::download(
            'members-'.now()->format('Ymd').'.csv',
            ['Name', 'Student ID', 'Programme', 'Email', 'Status', 'Applied on', 'Reviewed by', 'Reviewed on'],
            $rows,
        );
    }

    private function filtered(Request $request)
    {
        $query = Membership::with('user', 'reviewer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)
                    ->orWhere('student_id', 'like', $term)
                    ->orWhere('programme', 'like', $term));
            });

        return DateFilter::apply($query, $request, 'created_at');
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

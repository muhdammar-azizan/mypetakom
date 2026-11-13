<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('student_id', 'like', $term)
                    ->orWhere('staff_id', 'like', $term));
            })
            ->orderBy('role')->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('users.form', ['account' => new User(['role' => User::ROLE_ADVISOR])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('users.index')->with('toast', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['account' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('toast', 'Account updated.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot deactivate your own account.');

        $user->update(['deactivated_at' => $user->isDeactivated() ? null : now()]);

        return back()->with('toast', $user->isDeactivated() ? 'Account deactivated.' : 'Account activated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        if ($user->membership) {
            Storage::delete($user->membership->student_card_path);
        }

        $user->delete();

        return redirect()->route('users.index')->with('toast', 'Account deleted.');
    }

    private function validated(Request $request, ?User $user): array
    {
        $isStudent = $request->input('role') === User::ROLE_STUDENT;

        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_STUDENT, User::ROLE_ADVISOR, User::ROLE_COORDINATOR])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'student_id' => [Rule::requiredIf($isStudent), 'nullable', 'string', 'max:20', Rule::unique(User::class)->ignore($user?->id)],
            'staff_id' => [Rule::requiredIf(! $isStudent), 'nullable', 'string', 'max:20', Rule::unique(User::class)->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'programme' => ['nullable', 'string', 'max:255'],
            'password' => [$user ? 'nullable' : 'required', Password::defaults()],
        ]);

        // A user only has the ID that matches their role.
        $data['student_id'] = $isStudent ? strtoupper($data['student_id']) : null;
        $data['staff_id'] = $isStudent ? null : strtoupper($data['staff_id']);

        return $data;
    }
}

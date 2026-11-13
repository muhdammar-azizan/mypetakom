<x-app-layout title="Review Membership">
    <x-slot name="header">Review Membership</x-slot>
    <x-slot name="actions"><a href="{{ route('members.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back to members</a></x-slot>

    @php $student = $membership->user; @endphp

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr] max-w-4xl">

        <x-card title="Student Card">
            @if ($membership->isImage())
                <a href="{{ route('membership.card', $membership) }}" target="_blank">
                    <img src="{{ route('membership.card', $membership) }}" alt="Student card" class="max-w-full rounded-lg border border-slate-200">
                </a>
            @else
                <a href="{{ route('membership.card', $membership) }}" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
                    <x-icon name="file" /> Open PDF
                </a>
            @endif
        </x-card>

        <div class="flex flex-col gap-5">
            <x-card title="Student">
                <dl class="flex flex-col gap-3 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Name</dt><dd class="font-medium text-right">{{ $student->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Student ID</dt><dd class="font-mono">{{ $student->student_id }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Programme</dt><dd class="font-medium text-right">{{ $student->programme ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="font-medium text-right break-all">{{ $student->email }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $student->phone ?? '-' }}</dd></div>
                    <div class="flex justify-between items-center gap-3"><dt class="text-slate-500">Status</dt><dd><x-badge :color="$membership->status_color">{{ ucfirst($membership->status) }}</x-badge></dd></div>
                    @if ($membership->reviewer)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Reviewed by</dt><dd class="font-medium text-right">{{ $membership->reviewer->name }}<br><span class="text-xs text-slate-500">{{ $membership->reviewed_at->format('d M Y') }}</span></dd></div>
                    @endif
                    @if ($membership->remarks)
                        <div><dt class="text-slate-500 mb-1">Remarks</dt><dd class="text-slate-700">{{ $membership->remarks }}</dd></div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Decision" x-data="{ rejecting: {{ $errors->has('remarks') ? 'true' : 'false' }} }">
                <div x-show="! rejecting" class="flex flex-wrap gap-3">
                    @unless ($membership->isApproved())
                        <form method="POST" action="{{ route('members.approve', $membership) }}">
                            @csrf <x-primary-button>Approve</x-primary-button>
                        </form>
                    @endunless
                    @if ($membership->status !== 'rejected')
                        <x-secondary-button @click="rejecting = true">Reject</x-secondary-button>
                    @endif
                </div>

                <form x-show="rejecting" style="display:none" method="POST" action="{{ route('members.reject', $membership) }}" class="flex flex-col gap-3">
                    @csrf
                    <div>
                        <x-input-label for="remarks" value="Reason for rejection" />
                        <textarea id="remarks" name="remarks" rows="3" required class="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('remarks') }}</textarea>
                        <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                    </div>
                    <div class="flex gap-3">
                        <x-danger-button>Confirm reject</x-danger-button>
                        <x-secondary-button @click="rejecting = false">Cancel</x-secondary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('members.destroy', $membership) }}" class="mt-4 pt-4 border-t border-slate-100" onsubmit="return confirm('Delete this membership record?')">
                    @csrf @method('DELETE')
                    <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Delete record</button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>

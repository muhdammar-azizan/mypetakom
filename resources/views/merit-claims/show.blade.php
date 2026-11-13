@php
    $user = auth()->user();
    $event = $claim->event;
    $isOwner = $claim->user_id === $user->id;
    $canChange = $isOwner && ! $claim->isLocked();
    $canReview = $user->isAdvisor() && $event->advisor_id === $user->id && $claim->status === 'submitted';
@endphp

<x-app-layout title="Merit Claim">
    <x-slot name="header">Merit Claim</x-slot>
    <x-slot name="actions"><a href="{{ route('merit-claims.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a></x-slot>

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr] max-w-5xl">
        <x-card title="Claim Details">
            <div class="flex flex-wrap gap-2 mb-4">
                <x-badge :color="$claim->status_color">{{ $claim->status_label }}</x-badge>
                <x-badge color="brand">{{ $event->level_label }}</x-badge>
            </div>

            <dl class="flex flex-col gap-3 text-[13.5px]">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Event</dt><dd class="font-medium text-right">{{ $event->title }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Date</dt><dd class="font-medium">{{ $event->starts_at->format('d M Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Student</dt><dd class="font-medium text-right">{{ $claim->user->name }} <span class="font-mono text-xs text-slate-500">{{ $claim->user->student_id }}</span></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Claimed role</dt><dd class="font-medium">{{ $claim->role_label }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Merit if approved</dt><dd class="font-mono font-semibold">{{ $claim->points }} points</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Event advisor</dt><dd class="font-medium">{{ $event->advisor->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Supporting letter</dt><dd><a href="{{ route('merit-claims.letter', $claim) }}" target="_blank" class="font-semibold text-brand-600">Open letter</a></dd></div>
            </dl>

            @if ($claim->reason)
                <div class="mt-4"><div class="text-[13px] font-semibold text-slate-700 mb-1">Explanation</div><p class="text-sm text-slate-700 whitespace-pre-line">{{ $claim->reason }}</p></div>
            @endif

            @if ($claim->remarks)
                <div role="alert" class="mt-4 px-3.5 py-3 bg-danger-50 border border-danger-500 rounded-lg text-sm text-danger-700"><span class="font-semibold">Rejected:</span> {{ $claim->remarks }}</div>
            @endif

            @if ($claim->reviewer)
                <p class="mt-4 text-xs text-slate-500">Reviewed by {{ $claim->reviewer->name }} on {{ $claim->reviewed_at->format('d M Y') }}.</p>
            @endif
        </x-card>

        <div class="flex flex-col gap-5">
            @if ($isOwner)
                <x-card title="Actions">
                    @if ($canChange)
                        <p class="text-sm text-slate-600 mb-4">{{ $claim->status === 'rejected' ? 'Fix the problem, then submit again.' : 'This claim is a draft. You can still edit or delete it. Once submitted it is locked.' }}</p>
                        <div class="flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('merit-claims.submit', $claim) }}" onsubmit="return confirm('Submit this claim? You will not be able to edit it afterwards.')">
                                @csrf <x-primary-button>Submit claim</x-primary-button>
                            </form>
                            <a href="{{ route('merit-claims.edit', $claim) }}"><x-secondary-button>Edit</x-secondary-button></a>
                        </div>
                        <form method="POST" action="{{ route('merit-claims.destroy', $claim) }}" class="mt-4 pt-4 border-t border-slate-100" onsubmit="return confirm('Delete this claim?')">
                            @csrf @method('DELETE')
                            <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Delete claim</button>
                        </form>
                    @elseif ($claim->status === 'submitted')
                        <p class="text-sm text-slate-600">Submitted. Waiting for {{ $event->advisor->name }} to review. You cannot edit or delete it now.</p>
                    @else
                        <p class="text-sm text-ok-600 font-semibold">Approved. Merit has been added to your account.</p>
                    @endif
                </x-card>
            @endif

            @if ($canReview)
                <x-card title="Decision" x-data="{ rejecting: {{ $errors->has('remarks') ? 'true' : 'false' }} }">
                    <div x-show="! rejecting" class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('merit-claims.approve', $claim) }}">@csrf <x-primary-button>Approve</x-primary-button></form>
                        <x-secondary-button @click="rejecting = true">Reject</x-secondary-button>
                    </div>
                    <form x-show="rejecting" style="display:none" method="POST" action="{{ route('merit-claims.reject', $claim) }}" class="flex flex-col gap-3">
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
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>

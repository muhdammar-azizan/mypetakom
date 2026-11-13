@php $isCoordinator = auth()->user()->isCoordinator(); @endphp

<x-app-layout title="Merit Application">
    <x-slot name="header">Merit Application</x-slot>
    <x-slot name="actions"><a href="{{ route('merit-applications.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a></x-slot>

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr] max-w-5xl">

        <div class="flex flex-col gap-5 min-w-0">
            <x-card title="Event">
                <div class="flex flex-wrap gap-2 mb-3">
                    <x-badge :color="$event->status_color">{{ $event->status_label }}</x-badge>
                    <x-badge color="brand">{{ $event->level_label }}</x-badge>
                </div>
                <a href="{{ route('events.show', $event) }}" class="font-head text-lg font-bold text-slate-900 hover:underline">{{ $event->title }}</a>
                <dl class="mt-3 flex flex-col gap-2 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Date</dt><dd class="font-medium text-right">{{ $event->starts_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue</dt><dd class="font-medium text-right">{{ $event->venue }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Advisor</dt><dd class="font-medium text-right">{{ $event->advisor->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Approval letter</dt>
                        <dd><a href="{{ route('events.letter', $event) }}" target="_blank" class="font-semibold text-brand-600">Open letter</a></dd></div>
                </dl>
            </x-card>

            <x-card title="Merit points preview" subtitle="Points each committee member would receive at the {{ $event->level_label }} level.">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2">Student</th><th class="py-2">Position</th><th class="py-2 text-right">Points</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($event->committees->sortBy('position') as $c)
                            <tr>
                                <td class="py-2.5"><span class="font-semibold text-slate-800">{{ $c->user->name }}</span> <span class="font-mono text-xs text-slate-500">{{ $c->user->student_id }}</span></td>
                                <td class="py-2.5"><x-badge :color="$c->position === 'main_committee' ? 'solid' : 'brand'">{{ $c->position_label }}</x-badge></td>
                                <td class="py-2.5 text-right font-mono">{{ $scores[$c->position] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-slate-500">Participants who attend the event receive {{ $scores['participant'] }} points.</p>
            </x-card>
        </div>

        <div class="flex flex-col gap-5">
            <x-card title="Status">
                <div class="mb-3"><x-badge :color="$application->status_color">{{ ucfirst($application->status) }}</x-badge></div>
                <dl class="flex flex-col gap-2 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Applied</dt><dd class="font-medium">{{ $application->created_at->format('d M Y') }}</dd></div>
                    @if ($application->reviewer)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Reviewed by</dt><dd class="font-medium text-right">{{ $application->reviewer->name }}<br><span class="text-xs text-slate-500">{{ $application->reviewed_at->format('d M Y') }}</span></dd></div>
                    @endif
                </dl>
                @if ($application->remarks)
                    <div class="mt-3 px-3 py-2.5 bg-danger-50 rounded-lg text-sm text-danger-700"><span class="font-semibold">Remarks:</span> {{ $application->remarks }}</div>
                @endif
            </x-card>

            @if ($isCoordinator)
                <x-card title="Decision" x-data="{ rejecting: {{ $errors->has('remarks') ? 'true' : 'false' }} }">
                    <div x-show="! rejecting" class="flex flex-wrap gap-3">
                        @unless ($application->status === 'approved')
                            <form method="POST" action="{{ route('merit-applications.approve', $application) }}">@csrf <x-primary-button>Approve</x-primary-button></form>
                        @endunless
                        @if ($application->status !== 'rejected')
                            <x-secondary-button @click="rejecting = true">Reject</x-secondary-button>
                        @endif
                        @if ($application->status === 'approved')
                            <p class="text-sm text-slate-500">This application has been approved.</p>
                        @endif
                    </div>

                    <form x-show="rejecting" style="display:none" method="POST" action="{{ route('merit-applications.reject', $application) }}" class="flex flex-col gap-3">
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

@php $user = auth()->user(); @endphp

<x-app-layout :title="$event->title">
    <x-slot name="header">{{ $event->title }}</x-slot>
    <x-slot name="actions">
        <div class="flex items-center gap-3">
            @if ($canManage)
                <a href="{{ route('events.edit', $event) }}"><x-secondary-button>Edit</x-secondary-button></a>
            @endif
            <a href="{{ $user->isStudent() ? route('my-events.index') : route('events.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a>
        </div>
    </x-slot>

    @if ($event->status === 'postponed' || $event->status === 'cancelled')
        <div role="alert" class="mb-5 px-4 py-3 rounded-lg text-sm font-semibold {{ $event->status === 'cancelled' ? 'bg-danger-50 text-danger-700 border border-danger-500' : 'bg-gold-50 text-gold-700 border border-gold-300' }}">
            This event has been {{ $event->status }}.
        </div>
    @endif

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr]">
        <div class="flex flex-col gap-5 min-w-0">

            <x-card title="Event Details">
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-badge :color="$event->status_color">{{ $event->status_label }}</x-badge>
                    <x-badge color="brand">{{ $event->level_label }}</x-badge>
                    <x-badge color="gray">{{ $event->academic_year }} &middot; Sem {{ $event->semester }}</x-badge>
                </div>

                @if ($event->description)
                    <p class="text-sm text-slate-700 mb-4 whitespace-pre-line">{{ $event->description }}</p>
                @endif

                <dl class="flex flex-col gap-3 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Starts</dt><dd class="font-medium text-right">{{ $event->starts_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Ends</dt><dd class="font-medium text-right">{{ $event->ends_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue</dt><dd class="font-medium text-right">{{ $event->venue }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Coordinates</dt>
                        <dd class="text-right"><span class="font-mono text-xs">{{ $event->latitude }}, {{ $event->longitude }}</span>
                            <a href="{{ $event->map_url }}" target="_blank" class="ml-2 text-[13px] font-semibold text-brand-600">Map</a></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Advisor</dt><dd class="font-medium text-right">{{ $event->advisor->name }}</dd></div>
                    @if ($user->isCoordinator() || $canManage)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Approval letter</dt>
                            <dd><a href="{{ route('events.letter', $event) }}" target="_blank" class="font-semibold text-brand-600">Open letter</a></dd></div>
                    @endif
                </dl>

                @if ($canManage)
                    <form method="POST" action="{{ route('events.destroy', $event) }}" class="mt-5 pt-4 border-t border-slate-100" onsubmit="return confirm('Delete this event and its committee?')">
                        @csrf @method('DELETE')
                        <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Delete event</button>
                    </form>
                @endif
            </x-card>

            @if ($canManage)
                <x-card title="Attendance">
                    <p class="text-sm text-slate-600">Create a QR attendance slot so students can check in at the venue.</p>
                    <a href="{{ route('attendance.create', ['event' => $event->id]) }}" class="mt-3 inline-block"><x-secondary-button>Add attendance slot</x-secondary-button></a>
                </x-card>
            @endif

            @if ($canManage || $user->isCoordinator())
                @php $application = $event->meritApplication; @endphp
                <x-card title="Merit Application">
                    @if (! $application)
                        <p class="text-sm text-slate-600">No merit application has been submitted for this event.</p>
                        @if ($canManage)
                            <form method="POST" action="{{ route('events.merit.store', $event) }}" class="mt-3">
                                @csrf <x-primary-button>Apply for merit</x-primary-button>
                            </form>
                        @endif
                    @else
                        <div class="flex flex-wrap items-center gap-3">
                            <x-badge :color="$application->status_color">{{ ucfirst($application->status) }}</x-badge>
                            <a href="{{ route('merit-applications.show', $application) }}" class="text-[13px] font-semibold text-brand-600">View application</a>
                        </div>
                        @if ($application->remarks)
                            <p class="mt-3 px-3 py-2.5 bg-danger-50 rounded-lg text-sm text-danger-700"><span class="font-semibold">Remarks:</span> {{ $application->remarks }}</p>
                        @endif
                        @if ($canManage && $application->status === 'rejected')
                            <form method="POST" action="{{ route('events.merit.store', $event) }}" class="mt-3">
                                @csrf <x-primary-button>Apply again</x-primary-button>
                            </form>
                        @endif
                        @if ($canManage && $application->status === 'pending')
                            <form method="POST" action="{{ route('events.merit.destroy', $event) }}" class="mt-3">
                                @csrf @method('DELETE')
                                <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Withdraw application</button>
                            </form>
                        @endif
                    @endif
                </x-card>
            @endif

            <x-card title="Event Committee">
                @forelse ($event->committees->sortBy('position') as $c)
                    <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800 truncate">{{ $c->user->name }}</div>
                            <div class="text-xs text-slate-500"><span class="font-mono">{{ $c->user->student_id }}</span>{{ $c->position_title ? ' · '.$c->position_title : '' }}</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <x-badge :color="$c->position === 'main_committee' ? 'solid' : 'brand'">{{ $c->position_label }}</x-badge>
                            @if ($canManage)
                                <form method="POST" action="{{ route('events.committee.destroy', [$event, $c]) }}" onsubmit="return confirm('Remove this member?')">
                                    @csrf @method('DELETE')
                                    <button class="text-[13px] font-semibold text-danger-600 hover:underline" aria-label="Remove {{ $c->user->name }}">Remove</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No committee members yet.</p>
                @endforelse

                @if ($canManage)
                    <form method="POST" action="{{ route('events.committee.store', $event) }}" class="mt-5 pt-4 border-t border-slate-100 grid sm:grid-cols-[1fr_1fr_1fr_auto] gap-3 items-end">
                        @csrf
                        <div>
                            <x-input-label for="student_id" value="Student ID" />
                            <x-text-input id="student_id" name="student_id" class="block w-full font-mono uppercase" :value="old('student_id')" placeholder="CB21091" required />
                        </div>
                        <div>
                            <x-input-label for="position" value="Position" />
                            <select id="position" name="position" class="block w-full min-h-[44px] rounded-lg border-slate-300 text-sm">
                                @foreach (\App\Models\EventCommittee::POSITIONS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="position_title" value="Title (optional)" />
                            <x-text-input id="position_title" name="position_title" class="block w-full" :value="old('position_title')" placeholder="Secretary" />
                        </div>
                        <x-primary-button>Add</x-primary-button>
                        <div class="sm:col-span-4"><x-input-error :messages="$errors->get('student_id')" /><x-input-error :messages="$errors->get('position')" /></div>
                    </form>
                @endif
            </x-card>
        </div>

        <x-card title="Event QR" subtitle="Scan to see this event's information." class="text-center">
            <div class="mx-auto w-48 h-48 [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</div>
            <a href="{{ $event->public_url }}" target="_blank" class="mt-3 inline-block text-[13px] font-semibold text-brand-600">Open public page</a>
        </x-card>
    </div>
</x-app-layout>

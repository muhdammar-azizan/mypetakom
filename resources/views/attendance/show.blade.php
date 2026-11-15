@php $event = $slot->event; @endphp

<x-app-layout :title="$slot->title">
    <x-slot name="header">{{ $slot->title }}</x-slot>
    <x-slot name="actions">
        <div class="flex items-center gap-3">
            @if ($canManage)
                <a href="{{ route('attendance.edit', $slot) }}"><x-secondary-button>Edit</x-secondary-button></a>
            @endif
            <a href="{{ route('attendance.index') }}" class="text-sm font-semibold text-brand-600">&larr; Back</a>
        </div>
    </x-slot>

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr]">
        <div class="flex flex-col gap-5 min-w-0">
            <x-card title="Slot Details">
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-badge :color="$slot->window_color">{{ ucfirst($slot->window) }}</x-badge>
                    <x-badge :color="$event->status_color">Event {{ strtolower($event->status_label) }}</x-badge>
                </div>
                <dl class="flex flex-col gap-3 text-[13.5px]">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Event</dt><dd class="font-medium text-right"><a href="{{ route('events.show', $event) }}" class="text-brand-600">{{ $event->title }}</a></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Opens</dt><dd class="font-medium">{{ $slot->opens_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Closes</dt><dd class="font-medium">{{ $slot->closes_at->format('d M Y, h:i A') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Allowed distance</dt><dd class="font-medium">{{ $slot->radius_m }} m</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Venue</dt><dd class="font-medium text-right">{{ $event->venue }}</dd></div>
                </dl>

                @if ($canManage)
                    <form method="POST" action="{{ route('attendance.destroy', $slot) }}" class="mt-5 pt-4 border-t border-slate-100" onsubmit="return confirm('Delete this slot and all its check-ins?')">
                        @csrf @method('DELETE')
                        <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Delete slot</button>
                    </form>
                @endif
            </x-card>

            <x-card :title="'Check-ins ('.$slot->attendances->count().')'">
                @forelse ($slot->attendances->sortByDesc('checked_in_at') as $a)
                    <div class="flex items-center justify-between gap-3 py-2.5 border-t border-slate-100 first:border-t-0">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800 truncate">{{ $a->user->name }}</div>
                            <div class="text-xs text-slate-500"><span class="font-mono">{{ $a->user->student_id }}</span> &middot; {{ $a->checked_in_at->format('h:i A') }} &middot; {{ $a->distance_m }} m from venue</div>
                        </div>
                        @if ($canManage)
                            <form method="POST" action="{{ route('attendance.records.destroy', [$slot, $a]) }}" onsubmit="return confirm('Remove this check-in?')">
                                @csrf @method('DELETE')
                                <button class="text-[13px] font-semibold text-danger-600 hover:underline" aria-label="Remove {{ $a->user->name }}">Remove</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No one has checked in yet.</p>
                @endforelse
            </x-card>
        </div>

        @if ($slot->rotating_qr)
            <x-card title="Check-in QR" subtitle="Rotating code: show this on a screen at the venue." class="text-center"
                    x-data="{
                        left: {{ \App\Support\RotatingCode::secondsLeft() }},
                        url: '{{ $slot->scan_url }}',
                        busy: false,
                        async refresh() {
                            if (this.busy) return;
                            this.busy = true;
                            try {
                                const r = await fetch('{{ route('attendance.qr', $slot) }}', { headers: { Accept: 'application/json' } });
                                if (! r.ok) throw new Error('failed');
                                const d = await r.json();
                                this.$refs.box.innerHTML = d.svg;
                                this.url = d.url;
                                this.left = d.refresh_in;
                            } catch (e) {
                                this.left = 5;
                            } finally {
                                this.busy = false;
                            }
                        },
                        start() {
                            setInterval(() => { this.left--; if (this.left <= 0) this.refresh(); }, 1000);
                        }
                    }" x-init="start()">
                <div x-ref="box" class="mx-auto w-52 h-52 [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</div>
                <p class="mt-3 text-xs text-slate-500" aria-live="off">New code in <span class="font-mono font-semibold text-slate-700" x-text="Math.max(left, 0)"></span> s</p>
                <p class="mt-1 text-xs text-slate-400">Each code only works for about a minute, so a photo of it cannot be shared with someone who is not there.</p>
                <div class="mt-3 flex justify-center gap-3">
                    <a :href="url" target="_blank" class="text-[13px] font-semibold text-brand-600">Open page</a>
                </div>
            </x-card>
        @else
            <x-card title="Check-in QR" subtitle="Students scan this at the venue." class="text-center">
                <div class="mx-auto w-52 h-52 [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</div>
                <p class="mt-3 text-xs text-slate-500 break-all">{{ $slot->checkin_url }}</p>
                <div class="mt-3 flex justify-center gap-3">
                    <a href="{{ $slot->checkin_url }}" target="_blank" class="text-[13px] font-semibold text-brand-600">Open page</a>
                    <button type="button" onclick="window.print()" class="text-[13px] font-semibold text-brand-600">Print</button>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>

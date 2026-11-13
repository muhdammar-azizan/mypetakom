<x-app-layout title="Membership">
    <x-slot name="header">Membership</x-slot>

    <div class="grid gap-5 items-start lg:grid-cols-[1.5fr_1fr] max-w-4xl">

        <x-card title="Membership Status">
            @if (! $membership)
                <p class="text-sm text-slate-600">You are not a Petakom member yet. Upload a clear photo or PDF of your student card to apply.</p>
            @else
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <x-badge :color="$membership->status_color">{{ ucfirst($membership->status) }}</x-badge>
                    <span class="text-xs text-slate-500">Submitted {{ $membership->updated_at->format('d M Y') }}</span>
                </div>

                @if ($membership->isApproved())
                    <div class="flex items-start gap-2.5 px-3.5 py-3 bg-ok-50 rounded-lg text-sm text-ok-600">
                        <x-icon name="check" class="mt-0.5 shrink-0" />
                        <span>Your membership was approved on {{ $membership->reviewed_at?->format('d M Y') }}. You can now join Petakom events and earn merit.</span>
                    </div>
                @elseif ($membership->status === 'rejected')
                    <div role="alert" class="px-3.5 py-3 bg-danger-50 border border-danger-500 rounded-lg text-sm text-danger-700">
                        <p class="font-semibold">Your application was rejected.</p>
                        <p class="mt-1">{{ $membership->remarks }}</p>
                    </div>
                @else
                    <p class="text-sm text-slate-600">Your application is waiting for the Petakom Coordinator to review it.</p>
                @endif

                <div class="mt-5">
                    <p class="text-[13px] font-semibold text-slate-700 mb-2">Uploaded student card</p>
                    @if ($membership->isImage())
                        <a href="{{ route('membership.card', $membership) }}" target="_blank">
                            <img src="{{ route('membership.card', $membership) }}" alt="Student card" class="max-h-56 rounded-lg border border-slate-200">
                        </a>
                    @else
                        <a href="{{ route('membership.card', $membership) }}" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
                            <x-icon name="file" /> Open PDF
                        </a>
                    @endif
                </div>
            @endif
        </x-card>

        @unless ($membership?->isApproved())
            <x-card :title="$membership ? ($membership->status === 'rejected' ? 'Resubmit Card' : 'Replace Card') : 'Apply for Membership'">
                <form method="POST" action="{{ route('membership.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <div>
                        <x-input-label for="student_card" value="Student card (JPG, PNG or PDF, max 4 MB)" />
                        <input id="student_card" type="file" name="student_card" accept=".jpg,.jpeg,.png,.pdf" required
                               class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold hover:file:bg-brand-100">
                        <x-input-error :messages="$errors->get('student_card')" class="mt-2" />
                    </div>
                    <x-primary-button>{{ $membership ? 'Submit again' : 'Submit application' }}</x-primary-button>
                </form>

                @if ($membership)
                    <form method="POST" action="{{ route('membership.destroy') }}" class="mt-3" onsubmit="return confirm('Cancel your membership application?')">
                        @csrf @method('DELETE')
                        <button class="text-[13px] font-semibold text-danger-600 hover:underline min-h-[44px]">Cancel application</button>
                    </form>
                @endif
            </x-card>
        @endunless
    </div>
</x-app-layout>

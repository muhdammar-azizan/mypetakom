<x-guest-layout>
    <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Verify your e-mail</h2>
    <p class="text-sm text-slate-500 mb-7">
        We sent a link to <strong class="text-slate-700">{{ auth()->user()->email }}</strong>. Open it to confirm this address.
        You can keep using MyPetakom meanwhile.
    </p>

    @if (session('status') === 'verification-link-sent')
        <div role="status" class="mb-5 flex items-start gap-2.5 px-3.5 py-3 bg-ok-50 border border-ok-500 rounded-lg">
            <x-icon name="check" class="shrink-0 text-ok-600 mt-0.5" />
            <span class="text-[13.5px] text-ok-600 leading-snug">A new link has been sent. Check your inbox and spam folder.</span>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Resend link</x-primary-button>
        </form>
        <a href="{{ route('dashboard') }}"><x-secondary-button type="button">Continue to dashboard</x-secondary-button></a>
    </div>

    <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500 leading-relaxed">
        Wrong address? Staff can change it in <a href="{{ route('profile.edit') }}" class="font-semibold text-brand-600">My Profile</a>.
        Students: contact the <a href="mailto:coordinator@petakom.umpsa.edu.my" class="font-semibold text-brand-600">Petakom Coordinator</a>.
    </div>
</x-guest-layout>

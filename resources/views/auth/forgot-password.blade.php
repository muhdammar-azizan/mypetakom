<x-guest-layout>
    <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Forgot password?</h2>
    <p class="text-sm text-slate-500 mb-7">Enter your student ID, staff ID or e-mail. If it matches an account, we will e-mail a link to choose a new password.</p>

    @if (session('status'))
        <div role="status" class="mb-5 flex items-start gap-2.5 px-3.5 py-3 bg-ok-50 border border-ok-500 rounded-lg">
            <x-icon name="check" class="shrink-0 text-ok-600 mt-0.5" />
            <span class="text-[13.5px] text-ok-600 leading-snug">{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <x-input-label for="login" value="Student ID / Staff ID / Email" />
            <div class="relative">
                <x-icon name="mail" :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <x-text-input id="login" name="login" type="text" :value="old('login')" required autofocus autocomplete="username"
                    class="block w-full pl-10 {{ $errors->has('login') ? 'border-danger-500' : '' }}" />
            </div>
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Send reset link</x-primary-button>
    </form>

    <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500 leading-relaxed">
        <a href="{{ route('login') }}" class="font-semibold text-brand-600">&larr; Back to log in</a>
    </div>
</x-guest-layout>

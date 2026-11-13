<x-guest-layout>
    <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Log In</h2>
    <p class="text-sm text-slate-500 mb-7">Enter your credentials to continue.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <!-- Student ID / Staff ID / Email -->
        <div>
            <x-input-label for="login" value="Student ID / Email" />
            <div class="relative">
                <x-icon name="mail" :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <x-text-input id="login" name="login" type="text" :value="old('login')" required autofocus autocomplete="username"
                    placeholder="studentid@umpsa.edu.my"
                    class="block w-full pl-10 {{ $errors->has('login') ? 'border-danger-500' : '' }}" />
            </div>
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <x-input-label for="password" value="Password" />
            <div class="relative">
                <x-icon name="lock" :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <x-text-input id="password" name="password" ::type="show ? 'text' : 'password'" type="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    class="block w-full pl-10 pr-12 {{ $errors->has('login') ? 'border-danger-500' : '' }}" />
                <button type="button" @click="show = ! show" :aria-label="show ? 'Hide password' : 'Show password'"
                        class="absolute right-0.5 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center text-slate-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-600">
                    <x-icon name="eye" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember_me" class="flex items-center gap-2.5 min-h-[44px] text-sm text-slate-700 cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember" class="w-5 h-5 rounded-md border-slate-300 text-brand-600 focus:ring-brand-300">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-[13.5px] font-semibold text-brand-600 hover:text-brand-700 no-underline min-h-[44px] flex items-center">Forgot password?</a>
            @endif
        </div>

        <x-primary-button class="w-full mt-1">Log In</x-primary-button>

        @if ($errors->has('login'))
            <div role="alert" class="flex items-start gap-2.5 px-3.5 py-3 bg-danger-50 border border-danger-500 rounded-lg">
                <x-icon name="x-circle" class="shrink-0 text-danger-700 mt-0.5" />
                <span class="text-[13.5px] text-danger-700 leading-snug">{{ $errors->first('login') }}</span>
            </div>
        @endif
    </form>

    <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500 leading-relaxed space-y-1">
        <p>New student? <a href="{{ route('register') }}" class="font-semibold text-brand-600">Create an account</a>.</p>
        <p>Having trouble accessing your account? Contact your <a href="mailto:coordinator@petakom.umpsa.edu.my" class="font-semibold text-brand-600">Petakom Coordinator</a> for assistance.</p>
    </div>
</x-guest-layout>

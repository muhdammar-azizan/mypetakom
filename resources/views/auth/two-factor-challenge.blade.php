<x-guest-layout>
    <div x-data="{ recovery: {{ $recovery || $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Two-factor check</h2>
        <p class="text-sm text-slate-500 mb-7" x-show="! recovery">Open your authenticator app and enter the 6-digit code for MyPetakom.</p>
        <p class="text-sm text-slate-500 mb-7" x-show="recovery" style="display:none">Enter one of the recovery codes you saved when you turned on two-factor. Each code works once.</p>

        <form method="POST" action="{{ route('two-factor.store') }}" class="flex flex-col gap-5">
            @csrf

            <div x-show="! recovery">
                <x-input-label for="code" value="Authentication code" />
                <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]*"
                    x-bind:disabled="recovery" autofocus placeholder="123456"
                    class="block w-full font-mono text-lg tracking-[0.3em] {{ $errors->has('code') ? 'border-danger-500' : '' }}" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div x-show="recovery" style="display:none">
                <x-input-label for="recovery_code" value="Recovery code" />
                <x-text-input id="recovery_code" name="recovery_code" type="text" autocomplete="off" maxlength="30"
                    x-bind:disabled="! recovery" placeholder="abcde-fghij"
                    class="block w-full font-mono {{ $errors->has('recovery_code') ? 'border-danger-500' : '' }}" />
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
            </div>

            <x-primary-button class="w-full">Continue</x-primary-button>
        </form>

        <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500 leading-relaxed space-y-1">
            <p>
                <button type="button" class="font-semibold text-brand-600" x-show="! recovery" @click="recovery = true">Lost your phone? Use a recovery code</button>
                <button type="button" class="font-semibold text-brand-600" x-show="recovery" style="display:none" @click="recovery = false">Use the code from my app instead</button>
            </p>
            <p><a href="{{ route('login') }}" class="font-semibold text-brand-600">&larr; Back to log in</a></p>
        </div>
    </div>
</x-guest-layout>

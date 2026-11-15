{{-- Two-factor sign-in card. Shown to advisors and coordinators. Data comes from SettingsController::edit. --}}
@php
    $bag = $errors->twoFactor;
    // Which panel to reopen after a wrong password. Only these two values are ever put into the page's JavaScript.
    $openPanel = in_array(old('panel'), ['renew', 'disable'], true) ? old('panel') : 'disable';
@endphp

<x-card title="Two-factor sign-in" subtitle="Ask for a code from an authenticator app after the password.">
    @if ($twoFactor['state'] === 'on')
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <x-badge color="green">On</x-badge>
            <span class="text-sm {{ $twoFactor['codes_left'] <= 2 ? 'text-danger-700 font-semibold' : 'text-slate-600' }}">
                {{ $twoFactor['codes_left'] }} recovery {{ \Illuminate\Support\Str::plural('code', $twoFactor['codes_left']) }} left
            </span>
        </div>

        <div class="flex flex-col gap-3" x-data="{ panel: {{ $bag->has('password') ? "'".$openPanel."'" : 'null' }} }">
            <div class="flex flex-wrap gap-3">
                <x-secondary-button type="button" @click="panel = panel === 'renew' ? null : 'renew'">Make new recovery codes</x-secondary-button>
                <x-secondary-button type="button" @click="panel = panel === 'disable' ? null : 'disable'">Turn off</x-secondary-button>
            </div>

            <form x-show="panel === 'renew'" style="display:none" method="POST" action="{{ route('two-factor.recovery') }}" class="flex flex-col gap-3 max-w-sm">
                @csrf
                <input type="hidden" name="panel" value="renew">
                <p class="text-sm text-slate-600">The old recovery codes stop working.</p>
                <div>
                    <x-input-label for="tf_renew_password" value="Confirm with your password" />
                    <x-text-input id="tf_renew_password" name="password" type="password" class="block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$openPanel === 'renew' ? $bag->get('password') : []" class="mt-2" />
                </div>
                <div><x-primary-button>Make new codes</x-primary-button></div>
            </form>

            <form x-show="panel === 'disable'" style="display:none" method="POST" action="{{ route('two-factor.disable') }}" class="flex flex-col gap-3 max-w-sm"
                  onsubmit="return confirm('Turn off two-factor sign-in?')">
                @csrf @method('DELETE')
                <input type="hidden" name="panel" value="disable">
                <p class="text-sm text-slate-600">Your account will be protected by the password alone.</p>
                <div>
                    <x-input-label for="tf_off_password" value="Confirm with your password" />
                    <x-text-input id="tf_off_password" name="password" type="password" class="block w-full" autocomplete="current-password" required />
                    <x-input-error :messages="$openPanel === 'disable' ? $bag->get('password') : []" class="mt-2" />
                </div>
                <div><x-danger-button>Turn off two-factor</x-danger-button></div>
            </form>
        </div>

    @elseif ($twoFactor['state'] === 'setup')
        <ol class="list-decimal pl-5 text-sm text-slate-700 flex flex-col gap-1 mb-4">
            <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, Authy or similar).</li>
            <li>Scan the QR code, or type the key.</li>
            <li>Enter the 6-digit code the app shows.</li>
        </ol>

        <div class="flex flex-wrap items-start gap-5">
            <div class="w-44 h-44 shrink-0 border border-slate-200 rounded-xl p-2 bg-white [&>svg]:w-full [&>svg]:h-full" role="img" aria-label="QR code for your authenticator app">{!! $twoFactor['qr'] !!}</div>
            <div class="min-w-0 flex-1 basis-60">
                <div class="text-xs text-slate-500 mb-1">Can't scan? Type this key:</div>
                <div class="font-mono text-sm text-slate-900 break-all bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 select-all">{{ $twoFactor['key'] }}</div>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-col gap-3">
                    @csrf
                    <div>
                        <x-input-label for="tf_code" value="6-digit code" />
                        <x-text-input id="tf_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required
                            class="block w-full max-w-[12rem] font-mono text-lg tracking-[0.3em] {{ $bag->has('code') ? 'border-danger-500' : '' }}" placeholder="123456" />
                        <x-input-error :messages="$bag->get('code')" class="mt-2" />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-primary-button>Turn on</x-primary-button>
                    </div>
                </form>
                <form method="POST" action="{{ route('two-factor.cancel') }}" class="mt-2">
                    @csrf
                    <button class="text-[13px] font-semibold text-slate-600 hover:underline min-h-[44px]">Cancel setup</button>
                </form>
            </div>
        </div>

    @else
        <p class="text-sm text-slate-600 mb-4">
            Even if someone learns your password, they cannot sign in without the code from your phone.
            Recommended for coordinators and advisors.
        </p>
        <form method="POST" action="{{ route('two-factor.start') }}" class="flex flex-col gap-3 max-w-sm">
            @csrf
            <div>
                <x-input-label for="tf_start_password" value="Confirm with your password" />
                <x-text-input id="tf_start_password" name="password" type="password" class="block w-full" autocomplete="current-password" required />
                <x-input-error :messages="$bag->get('password')" class="mt-2" />
            </div>
            <div><x-primary-button>Set up two-factor</x-primary-button></div>
        </form>
    @endif
</x-card>

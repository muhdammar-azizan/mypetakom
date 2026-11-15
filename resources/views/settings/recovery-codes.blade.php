@php $codesText = implode(PHP_EOL, $codes); @endphp

<x-app-layout title="Recovery codes">
    <x-slot name="header">{{ $heading }}</x-slot>
    <x-slot name="actions"><a href="{{ route('settings.edit') }}" class="text-sm font-semibold text-brand-600">Done, back to settings &rarr;</a></x-slot>

    <div class="max-w-xl flex flex-col gap-5">
        <div role="alert" class="px-4 py-3 rounded-xl border bg-gold-50 border-gold-300 text-gold-700 text-sm font-semibold">
            Save these codes now. They are shown only once. Each code works one time, if you lose your phone.
        </div>

        <x-card title="Your recovery codes" subtitle="Keep them somewhere safe, like a password manager. Anyone with a code and your password can sign in."
                x-data="{
                    text: @js($codesText),
                    save() {
                        const blob = new Blob(['MyPetakom recovery codes\n\n' + this.text + '\n'], { type: 'text/plain;charset=utf-8' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'mypetakom-recovery-codes.txt';
                        link.click();
                        URL.revokeObjectURL(link.href);
                    }
                }">
            <ul class="grid grid-cols-2 gap-2 font-mono text-[15px] text-slate-900">
                @foreach ($codes as $code)
                    <li class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-center">{{ $code }}</li>
                @endforeach
            </ul>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-primary-button type="button" @click="save()">Download as text file</x-primary-button>
                <x-secondary-button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(text)">Copy</x-secondary-button>
            </div>
        </x-card>
    </div>
</x-app-layout>

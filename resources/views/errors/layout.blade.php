<x-public-layout :title="$heading">
    <div class="text-center py-4">
        <div class="font-mono text-5xl font-bold text-brand-600">{{ $code }}</div>
        <h1 class="font-head text-xl font-bold text-slate-900 mt-3">{{ $heading }}</h1>
        <p class="text-sm text-slate-600 mt-2">{{ $message }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"><x-primary-button type="button">{{ auth()->check() ? 'Back to dashboard' : 'Go to login' }}</x-primary-button></a>
            <a href="javascript:history.back()"><x-secondary-button type="button">Go back</x-secondary-button></a>
        </div>
    </div>
</x-public-layout>

<x-app-layout title="Dashboard">
    <x-slot name="header">Dashboard</x-slot>

    <x-card>
        <p class="font-head text-lg font-bold text-slate-900">Welcome, {{ auth()->user()->name }}</p>
        <p class="mt-1 text-sm text-slate-500">
            You are logged in as <x-badge color="{{ auth()->user()->isCoordinator() ? 'solid' : (auth()->user()->isAdvisor() ? 'gold' : 'brand') }}">{{ auth()->user()->role_label }}</x-badge>.
        </p>
    </x-card>
</x-app-layout>

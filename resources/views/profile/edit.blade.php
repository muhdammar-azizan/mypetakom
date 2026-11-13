<x-app-layout title="My Profile">
    <x-slot name="header">My Profile</x-slot>

    <div class="space-y-5 max-w-2xl">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>
</x-app-layout>

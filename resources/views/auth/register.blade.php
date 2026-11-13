<x-guest-layout>
    <h2 class="font-head text-[26px] font-bold text-slate-900 mb-1.5">Create Account</h2>
    <p class="text-sm text-slate-500 mb-7">Register as a Petakom student member.</p>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-4">
        @csrf

        <div>
            <x-input-label for="name" value="Full Name" />
            <x-text-input id="name" class="block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="student_id" value="Student ID" />
            <x-text-input id="student_id" class="block w-full font-mono uppercase" type="text" name="student_id" :value="old('student_id')" required placeholder="CB21091" />
            <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="programme" value="Programme" />
            <x-text-input id="programme" class="block w-full" type="text" name="programme" :value="old('programme')" required placeholder="BCS (Software Engineering)" />
            <x-input-error :messages="$errors->get('programme')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="studentid@adab.umpsa.edu.my" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="block w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm Password" />
            <x-text-input id="password_confirmation" class="block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full mt-2">Register</x-primary-button>
    </form>

    <div class="mt-7 pt-5 border-t border-slate-200 text-[12.5px] text-slate-500">
        Already registered? <a href="{{ route('login') }}" class="font-semibold text-brand-600">Log in</a>.
    </div>
</x-guest-layout>

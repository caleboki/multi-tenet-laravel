@extends('layouts.app')

@section('title', 'Your profile')

@section('content')
    <h1 class="text-2xl font-semibold">Your profile</h1>

    <section aria-labelledby="details-heading" class="flex max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="details-heading" class="text-lg font-semibold">Your details</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            The administrators of every organization you belong to see these details.
        </p>

        <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-1">
                <p class="text-sm font-medium">Email</p>
                <p class="text-sm">{{ $user->email }}</p>
            </div>

            <x-input name="name" label="Name" :value="$user->name" autocomplete="name" bag="updateProfileInformation" required />
            <x-input name="phone" label="Phone (optional)" type="tel" :value="$user->phone" autocomplete="tel" bag="updateProfileInformation" />

            <x-button class="self-start">Save details</x-button>
        </form>
    </section>

    <section aria-labelledby="password-heading" class="flex max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="password-heading" class="text-lg font-semibold">Change password</h2>

        <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-input name="current_password" label="Current password" type="password" autocomplete="current-password" bag="updatePassword" required />
            <x-input name="password" label="New password" type="password" autocomplete="new-password" hint="At least 8 characters." bag="updatePassword" required />
            <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" bag="updatePassword" required />

            <x-button class="self-start">Change password</x-button>
        </form>
    </section>
@endsection

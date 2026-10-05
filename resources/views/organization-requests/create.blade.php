@extends('layouts.guest')

@section('title', 'Request an organization')

@section('content')
    <div class="flex flex-col gap-2">
        <h1 class="text-xl font-semibold">Request an organization</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            A platform operator reviews each request. Once it's approved, you become the organization's first administrator.
        </p>
    </div>

    @guest
        <p class="text-sm">
            Already have an account?
            <a href="{{ route('login') }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Sign in</a>
            and you'll come back here.
        </p>
    @endguest

    <form method="POST" action="{{ route('organization-requests.store') }}" class="flex flex-col gap-4">
        @csrf

        <x-input name="organization_name" label="Organization name" maxlength="120" required />
        <x-input name="contact_email" label="Organization contact email" type="email" required />

        @guest
            <fieldset class="flex flex-col gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <legend class="text-sm font-semibold">Your account</legend>

                <x-input name="name" label="Your name" autocomplete="name" required />
                <x-input name="email" label="Your email" type="email" autocomplete="email" required />
                <x-input name="phone" label="Phone (optional)" type="tel" autocomplete="tel" />
                <x-input name="password" label="Password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
                <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

                <x-adult-confirmation />
            </fieldset>
        @endguest

        <x-button>Send request</x-button>
    </form>
@endsection

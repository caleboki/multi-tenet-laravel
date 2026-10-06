@extends('layouts.guest')

@section('title', $organization?->acceptsSignups() ? "Join {$organization->name}" : 'Sign-up link')

@section('content')
    @if ($organization === null)
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Sign-up link</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                This link is no longer valid. Ask the organization for its current sign-up link.
            </p>
        </div>
    @elseif (! $organization->acceptsSignups())
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Sign-up link</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                This organization isn't accepting sign-ups right now.
            </p>
        </div>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Join {{ $organization->name }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Ask to join as a volunteer. An administrator will review your request.
            </p>
        </div>

        <x-error-alert field="membership" />

        @auth
            @if ($refusal !== null)
                <div role="status" class="flex flex-col gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-100">
                    <p>{{ $refusal }}</p>
                    <a href="{{ route('dashboard') }}" class="self-start rounded-sm font-medium underline focus-visible:outline-2 focus-visible:outline-indigo-600">Go to your dashboard</a>
                </div>
            @else
                <form method="POST" action="{{ route('join.store', $signupToken) }}" class="flex flex-col gap-4">
                    @csrf

                    <p class="text-sm">
                        You're signed in as {{ auth()->user()->name }} ({{ auth()->user()->email }}).
                    </p>

                    <x-button>Request to join</x-button>
                </form>
            @endif
        @else
            <p class="text-sm">
                Already have an account?
                <a href="{{ route('login') }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Sign in</a>
                and you'll come back here.
            </p>

            <form method="POST" action="{{ route('join.store', $signupToken) }}" class="flex flex-col gap-4">
                @csrf

                <x-input name="name" label="Name" autocomplete="name" required />
                <x-input name="email" label="Email" type="email" autocomplete="email" required />
                <x-input name="phone" label="Phone (optional)" type="tel" autocomplete="tel" />
                <x-input name="password" label="Password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
                <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

                <x-adult-confirmation />

                <x-button>Create account and ask to join</x-button>
            </form>
        @endauth
    @endif
@endsection

@extends('layouts.guest')

@section('title', $invitation ? "Join {$invitation->organization->name}" : 'Invitation no longer valid')

@section('content')
    @if ($invitation === null)
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Invitation no longer valid</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                This invitation link has expired or has already been used.
                Ask your organization's administrator to send you a new invitation.
            </p>
        </div>
    @elseif ($hasAccount)
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Join {{ $invitation->organization->name }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                You already have an account with {{ $invitation->email }}. Sign in to accept this invitation.
            </p>
        </div>

        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            Sign in
        </a>
    @else
        <div class="flex flex-col gap-2">
            <h1 class="text-xl font-semibold">Join {{ $invitation->organization->name }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                You've been invited to join as {{ Str::lower($invitation->role->label()) }}. Set up your account to accept.
            </p>
        </div>

        <form method="POST" action="{{ route('invitations.accept', $token) }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1">
                <p class="text-sm font-medium">Email</p>
                <p class="text-sm">{{ $invitation->email }}</p>
            </div>

            <x-input name="name" label="Name" :value="$invitation->name" autocomplete="name" required />
            <x-input name="phone" label="Phone (optional)" type="tel" autocomplete="tel" />
            <x-input name="password" label="Password" type="password" autocomplete="new-password" hint="At least 8 characters." required />
            <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

            <x-adult-confirmation />

            <x-button>Create account and join</x-button>
        </form>
    @endif
@endsection

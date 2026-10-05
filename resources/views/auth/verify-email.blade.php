@extends('layouts.guest')

@section('title', 'Verify your email address')

@section('content')
    <div class="flex flex-col gap-2">
        <h1 class="text-xl font-semibold">Verify your email address</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            We sent a verification link to {{ auth()->user()->email }}. Open it to continue.
            Requests you have made are only sent for review once your address is verified.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>Resend verification email</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button variant="secondary">Sign out</x-button>
        </form>
    </div>
@endsection

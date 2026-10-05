@extends('layouts.guest')

@section('title', 'Forgot your password?')

@section('content')
    <div class="flex flex-col gap-2">
        <h1 class="text-xl font-semibold">Forgot your password?</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Enter your email address and we'll send you a link to choose a new password.
        </p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
        @csrf

        <x-input name="email" label="Email" type="email" autocomplete="email" required autofocus />

        <x-button>Email me a reset link</x-button>
    </form>

    <a href="{{ route('login') }}" class="text-sm text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to sign in
    </a>
@endsection

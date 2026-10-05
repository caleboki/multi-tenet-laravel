@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1 class="text-xl font-semibold">Sign in</h1>

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
        @csrf

        <x-input name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-input name="password" label="Password" type="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-zinc-300 focus-visible:outline-2 focus-visible:outline-indigo-600 dark:border-zinc-700">
            Keep me signed in
        </label>

        <x-button>Sign in</x-button>
    </form>

    <a href="{{ route('password.request') }}" class="text-sm text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Forgot your password?
    </a>
@endsection

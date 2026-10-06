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

    @if (config('demo.enabled'))
        <section aria-labelledby="demo-accounts-heading" class="flex flex-col gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800">
            <h2 id="demo-accounts-heading" class="text-base font-semibold">Demo accounts</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Every demo account uses the password <code class="font-mono">{{ config('demo.password') }}</code>.
            </p>

            <ul class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach (config('demo.accounts') as $account)
                    <li>
                        <form method="POST" action="{{ route('login.store') }}" class="flex items-center justify-between gap-3 py-2">
                            @csrf
                            <input type="hidden" name="email" value="{{ $account['email'] }}">
                            <input type="hidden" name="password" value="{{ config('demo.password') }}">

                            <span class="flex min-w-0 flex-col text-sm">
                                <span class="truncate font-medium">{{ $account['email'] }}</span>
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $account['description'] }}</span>
                            </span>

                            <x-button variant="secondary" class="shrink-0">
                                Sign in<span class="sr-only"> as {{ $account['email'] }}</span>
                            </x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

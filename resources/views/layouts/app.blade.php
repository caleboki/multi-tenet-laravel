<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') · {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow dark:focus:bg-zinc-800">
            Skip to content
        </a>

        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <a href="{{ route('dashboard') }}" class="rounded-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        {{ config('app.name') }}
                    </a>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        <span class="sr-only">Current organization:</span>
                        {{ $currentOrganization?->name ?? 'No organization selected' }}
                    </p>

                    <x-org-switcher :memberships="$openMemberships" :current="$currentOrganization" />
                </div>

                <div class="flex items-center gap-4">
                    @can('operate-platform')
                        <a href="{{ route('operator.organizations.index') }}" class="rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                            Operator
                        </a>
                    @endcan

                    <a href="{{ route('profile.edit') }}" class="rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                        Profile
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button variant="secondary">Sign out</x-button>
                    </form>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8">
            <x-flash />

            @yield('content')
        </main>
    </body>
</html>

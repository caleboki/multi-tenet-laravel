<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow dark:focus:bg-zinc-800">
            Skip to content
        </a>

        <x-demo-banner />

        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-3">
                <a href="{{ url('/') }}" class="rounded-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ config('app.name') }}
                </a>

                <nav aria-label="Account" class="flex items-center gap-4 text-sm font-medium">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-sm text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-sm text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Sign in</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main" class="mx-auto flex max-w-5xl flex-col gap-12 px-4 py-16">
            <section aria-labelledby="hero-heading" class="flex max-w-2xl flex-col gap-5">
                <h1 id="hero-heading" class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Keep each organization’s volunteers in one place
                </h1>
                <p class="text-lg text-zinc-600 dark:text-zinc-400">
                    Invite volunteers, approve people who ask to join, and keep a roster that only your organization can see. One account works across every organization you help.
                </p>

                <div class="flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Go to your dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            Sign in
                        </a>
                    @endauth

                    <a href="{{ route('organization-requests.create') }}" class="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                        Request an organization
                    </a>
                </div>
            </section>

            <section aria-labelledby="how-heading" class="flex flex-col gap-4">
                <h2 id="how-heading" class="text-lg font-semibold">How it works</h2>

                <ol class="grid gap-4 sm:grid-cols-3">
                    <li class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">1. Request your organization</span>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Tell us its name. Once a platform operator approves it, you're its first administrator.</p>
                    </li>
                    <li class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">2. Bring in your volunteers</span>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Invite people by email, import a spreadsheet, or share your sign-up link and approve each request.</p>
                    </li>
                    <li class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                        <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">3. Manage your roster</span>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Search and filter your members, share the work with other administrators, and export the list whenever you need it.</p>
                    </li>
                </ol>
            </section>
        </main>
    </body>
</html>

@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-semibold">Welcome, {{ $user->name }}</h1>

    @unless ($user->hasVerifiedEmail())
        <div class="flex flex-col gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <p>Verify your email address to continue. Your requests are only sent for review once it is verified.</p>
            <a href="{{ route('verification.notice') }}" class="self-start font-medium underline focus-visible:outline-2 focus-visible:outline-indigo-600">
                Verify your email
            </a>
        </div>
    @endunless

    <section aria-labelledby="organizations-heading" class="flex flex-col gap-3">
        <h2 id="organizations-heading" class="text-lg font-semibold">Your organizations</h2>

        @if ($memberships->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">You're not an active member of any organization yet.</p>
        @else
            <ul class="flex flex-col divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ($memberships as $membership)
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <a href="{{ route('orgs.show', $membership->organization) }}" class="font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                            {{ $membership->organization->name }}
                        </a>
                        <x-status-badge>{{ $membership->role->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($joinRequests->isNotEmpty())
        <section aria-labelledby="join-requests-heading" class="flex flex-col gap-3">
            <h2 id="join-requests-heading" class="text-lg font-semibold">Your join requests</h2>

            <ul class="flex flex-col divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ($joinRequests as $joinRequest)
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <span class="font-medium">{{ $joinRequest->organization->name }}</span>

                        @if ($user->hasVerifiedEmail())
                            <x-status-badge tone="warning">Awaiting approval</x-status-badge>
                        @else
                            <x-status-badge tone="warning">Verify your email first</x-status-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

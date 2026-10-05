@extends('layouts.app')

@section('title', $organization->name)

@section('content')
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">{{ $organization->name }}</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Your role: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $membership->role->label() }}</span>
        </p>
    </div>

    <x-error-alert field="membership" />

    @can('manageMembers', $organization)
        <nav aria-label="Organization administration" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold">Administration</h2>
            <ul class="mt-2 flex flex-wrap gap-4 text-sm">
                <li>
                    <a href="{{ route('orgs.members.index', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                        Roster
                    </a>
                </li>
                <li>
                    <a href="{{ route('orgs.invitations.create', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                        Invite volunteer
                    </a>
                </li>
                <li>
                    <a href="{{ route('orgs.join-requests.index', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                        Join requests ({{ $joinRequestCount }})
                    </a>
                </li>
                <li>
                    <a href="{{ route('orgs.settings.edit', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                        Settings
                    </a>
                </li>
            </ul>
        </nav>
    @endcan

    <section aria-labelledby="leave-heading" class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="leave-heading" class="text-sm font-semibold">Leave {{ $organization->name }}</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Leaving ends your access to this organization straight away. You can ask to join again later through its sign-up link.
        </p>

        <form method="POST" action="{{ route('orgs.membership.destroy', $organization) }}">
            @csrf
            @method('DELETE')
            <x-button variant="secondary">Leave organization</x-button>
        </form>
    </section>
@endsection

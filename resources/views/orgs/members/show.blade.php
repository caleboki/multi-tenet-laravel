@extends('layouts.app')

@section('title', $membership->user->name)

@section('content')
    <a href="{{ route('orgs.members.index', $organization) }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to the roster
    </a>

    <h1 class="text-2xl font-semibold">{{ $membership->user->name }}</h1>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 rounded-lg border border-zinc-200 bg-white p-4 text-sm sm:grid-cols-[max-content_1fr] dark:border-zinc-800 dark:bg-zinc-900">
        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Name</dt>
        <dd>{{ $membership->user->name }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Email</dt>
        <dd>{{ $membership->user->email }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Phone</dt>
        <dd>{{ $membership->user->phone ?? 'Not provided' }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Role</dt>
        <dd>{{ $membership->role->label() }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Status</dt>
        <dd>
            @php($rosterStatus = \App\Enums\RosterStatus::fromMembership($membership->status))
            <x-status-badge :tone="$rosterStatus->tone()">{{ $rosterStatus->label() }}</x-status-badge>
        </dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Date joined</dt>
        <dd>{{ $membership->joined_at?->format('j M Y') ?? '—' }}</dd>
    </dl>

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        Members keep their own name, email and phone number up to date. You can't change them here.
    </p>
@endsection

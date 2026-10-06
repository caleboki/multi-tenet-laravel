@extends('layouts.guest')

@section('title', 'Demo inbox')
@section('width', 'max-w-3xl')

@section('content')
    <div class="flex flex-col gap-2">
        <h1 class="text-xl font-semibold">Demo inbox</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            This demo doesn't send real emails. Every email the app would have sent is listed here, newest first, so you can follow its links.
            Anyone using the demo can see this page, so don't use a password you use elsewhere.
        </p>
    </div>

    <form method="GET" action="{{ route('demo.inbox.index') }}" role="search" class="flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
            <x-input name="to" label="Show emails sent to" type="search" :value="$recipient" placeholder="you@example.com" />
        </div>
        <x-button variant="secondary">Filter</x-button>
    </form>

    @if ($emails->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-400">No emails yet. Invite someone, sign up, or ask for a password reset, then refresh this page.</p>
    @else
        <ul class="flex flex-col divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @foreach ($emails as $email)
                <li>
                    <a href="{{ route('demo.inbox.show', $email) }}" class="flex flex-col gap-1 px-4 py-3 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:flex-row sm:items-baseline sm:justify-between dark:hover:bg-zinc-800">
                        <span class="flex flex-col">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $email->recipients }}</span>
                            <span class="font-medium text-indigo-700 underline dark:text-indigo-300">{{ $email->subject }}</span>
                        </span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ $email->created_at->diffForHumans() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $emails->links() }}
    @endif
@endsection

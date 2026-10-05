@php
    $organization = request()->route('organization');
@endphp

@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Already handled')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">This has already been handled</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ $exception->getMessage() ?: 'Someone may have dealt with it already. Refresh the page to see the current state.' }}
        </p>

        @if ($organization instanceof \App\Models\Organization && request()->routeIs('operator.*'))
            <a href="{{ route('operator.organizations.show', $organization) }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Back to {{ $organization->name }}
            </a>
        @elseif ($organization instanceof \App\Models\Organization)
            <a href="{{ route('orgs.show', $organization) }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Back to {{ $organization->name }}
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Go to your dashboard
            </a>
        @endif
    </div>
@endsection

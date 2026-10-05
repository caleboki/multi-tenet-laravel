@php
    $organization = request()->route('organization');
@endphp

@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Access denied')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">You don't have access to this page</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Your role doesn't allow this action. If you think it should, ask an administrator.
        </p>

        @if ($organization instanceof \App\Models\Organization)
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

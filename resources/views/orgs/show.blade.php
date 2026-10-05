@extends('layouts.app')

@section('title', $organization->name)

@section('content')
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">{{ $organization->name }}</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Your role: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $membership->role->label() }}</span>
        </p>
    </div>

    @can('manageMembers', $organization)
        <nav aria-label="Organization administration" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold">Administration</h2>
            <ul class="mt-2 flex flex-wrap gap-4 text-sm">
                @stack('admin-links')
            </ul>
        </nav>
    @endcan
@endsection

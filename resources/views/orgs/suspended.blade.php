@extends('layouts.app')

@section('title', $organization->name.' is suspended')

@section('content')
    <div class="flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50 p-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
        <h1 class="text-xl font-semibold">{{ $organization->name }} is suspended</h1>
        <p class="text-sm">
            Access to this organization has been paused by the platform operators.
            You can still use any other organizations you belong to.
        </p>
        <a href="{{ route('dashboard') }}" class="self-start text-sm font-medium underline focus-visible:outline-2 focus-visible:outline-indigo-600">
            Go to your dashboard
        </a>
    </div>
@endsection

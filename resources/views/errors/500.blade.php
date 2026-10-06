{{-- Uses the guest layout on purpose: the app layout queries the database, which may be what failed. --}}
@extends('layouts.guest')

@section('title', 'Something went wrong')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">Something went wrong</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Something went wrong on our side. Please try again in a few minutes.
        </p>
        <a href="{{ url('/') }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Go to the home page</a>
    </div>
@endsection

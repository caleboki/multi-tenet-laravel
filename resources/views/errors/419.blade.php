@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Page expired')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">This page has expired</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            For your security, forms stop working after a while. Go back, refresh the page and try again.
        </p>
        <a href="{{ url()->previous() }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Go back</a>
    </div>
@endsection

@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Too many attempts')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">Too many attempts</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Please wait a minute, then try again.
        </p>
        <a href="{{ url()->previous() }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Go back</a>
    </div>
@endsection

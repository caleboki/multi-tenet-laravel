@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Page not found')

@section('content')
    <div class="flex flex-col gap-3">
        <h1 class="text-xl font-semibold">Page not found</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            We couldn't find that page. The link may be wrong, or the page isn't available to you.
        </p>

        @auth
            <a href="{{ route('dashboard') }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Go to your dashboard</a>
        @else
            <a href="{{ url('/') }}" class="self-start text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Go to the home page</a>
        @endauth
    </div>
@endsection

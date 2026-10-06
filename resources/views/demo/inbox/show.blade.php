@extends('layouts.guest')

@section('title', $email->subject)
@section('width', 'max-w-3xl')

@section('content')
    <a href="{{ route('demo.inbox.index') }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to the demo inbox
    </a>

    <div class="flex flex-col gap-1">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">To: {{ $email->recipients }}</p>
        <h1 class="text-xl font-semibold">{{ $email->subject }}</h1>
        <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $email->created_at->format('j M Y, H:i') }}</p>
    </div>

    @if ($email->links() !== [])
        <section aria-labelledby="links-heading" class="flex flex-col gap-2">
            <h2 id="links-heading" class="text-sm font-semibold">Links in this email</h2>
            <ul class="flex flex-col gap-2">
                @foreach ($email->links() as $link)
                    <li>
                        <a href="{{ $link }}" class="inline-flex max-w-full items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium break-all text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ $link }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs text-zinc-600 dark:text-zinc-400">Links open in this window, signed in as whoever is signed in now.</p>
        </section>
    @endif

    <section aria-labelledby="message-heading" class="flex flex-col gap-2">
        <h2 id="message-heading" class="text-sm font-semibold">Message</h2>
        <pre class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-50 p-4 font-sans text-sm whitespace-pre-wrap dark:border-zinc-800 dark:bg-zinc-950">{{ $email->text_body ?? 'This email has no plain-text version.' }}</pre>
    </section>
@endsection

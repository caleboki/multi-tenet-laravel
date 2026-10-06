@if (config('demo.enabled'))
    <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
        Demo mode: emails are not really sent.
        <a href="{{ route('demo.inbox.index') }}" class="font-medium underline focus-visible:outline-2 focus-visible:outline-indigo-600">Read them in the demo inbox</a>.
    </div>
@endif

@props([
    'variant' => 'primary',
    'type' => 'submit',
])

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50',
        'bg-indigo-600 text-white hover:bg-indigo-700' => $variant === 'primary',
        'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800' => $variant === 'secondary',
        'bg-red-600 text-white hover:bg-red-700' => $variant === 'danger',
    ]) }}
>
    {{ $slot }}
</button>

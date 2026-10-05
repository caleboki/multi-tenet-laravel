@props([
    'tone' => 'neutral',
])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200' => $tone === 'neutral',
    'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => $tone === 'success',
    'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' => $tone === 'warning',
    'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200' => $tone === 'danger',
]) }}>
    {{ $slot }}
</span>

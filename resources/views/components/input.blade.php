@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'bag' => 'default',
])

@php
    $id = $attributes->get('id', $name);
    $fieldErrors = $errors->getBag($bag);
    $describedBy = collect([
        $hint ? "{$id}-hint" : null,
        $fieldErrors->has($name) ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="text-sm font-medium">{{ $label }}</label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @unless ($type === 'password') value="{{ old($name, $value) }}" @endunless
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @error($name, $bag) aria-invalid="true" @enderror
        {{ $attributes->except('id')->class([
            'rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-indigo-600 dark:bg-zinc-950',
            'border-zinc-300 dark:border-zinc-700' => ! $fieldErrors->has($name),
            'border-red-600 dark:border-red-500' => $fieldErrors->has($name),
        ]) }}
    >

    @if ($hint)
        <p id="{{ $id }}-hint" class="text-xs text-zinc-600 dark:text-zinc-400">{{ $hint }}</p>
    @endif

    @error($name, $bag)
        <p id="{{ $id }}-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

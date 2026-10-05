@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'placeholder' => null,
])

@php
    $id = $attributes->get('id', $name);
    $selected = (string) old($name, $value);
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="text-sm font-medium">{{ $label }}</label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->except('id')->class([
            'rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-indigo-600 dark:bg-zinc-950',
            'border-zinc-300 dark:border-zinc-700' => ! $errors->has($name),
            'border-red-600 dark:border-red-500' => $errors->has($name),
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @error($name)
        <p id="{{ $id }}-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

@props([
    'field',
])

@error($field)
    <div role="alert" {{ $attributes->class('rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100') }}>
        {{ $message }}
    </div>
@enderror

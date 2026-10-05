@php
    $messages = [
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
    ];

    $status = session('status');
@endphp

@if ($status)
    <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
        {{ $messages[$status] ?? $status }}
    </div>
@endif

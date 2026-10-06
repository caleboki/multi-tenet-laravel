@php
    $fortifyMessages = [
        'verification-link-sent' => 'A new verification link has been sent to your email address.',
        'profile-information-updated' => 'Your details have been saved.',
        'password-updated' => 'Your password has been changed.',
    ];

    $tones = [
        'status' => ['role' => 'status', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'],
        'info' => ['role' => 'status', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-950 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-100'],
        'warning' => ['role' => 'alert', 'class' => 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100'],
    ];
@endphp

@foreach ($tones as $key => $tone)
    @if ($message = session($key))
        <div role="{{ $tone['role'] }}" data-tone="{{ $key }}" class="rounded-md border px-4 py-3 text-sm {{ $tone['class'] }}">
            {{ $fortifyMessages[$message] ?? $message }}
        </div>
    @endif
@endforeach

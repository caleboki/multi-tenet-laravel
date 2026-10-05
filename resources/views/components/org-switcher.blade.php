@props([
    'memberships',
    'current' => null,
])

@if ($memberships->isNotEmpty())
    <details class="relative">
        <summary class="cursor-pointer rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
            Switch organization
        </summary>

        <nav aria-label="Your organizations" class="absolute left-0 z-10 mt-2 min-w-56 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
            <ul>
                @foreach ($memberships as $membership)
                    @php($isCurrent = $current?->is($membership->organization) ?? false)

                    <li>
                        <a
                            href="{{ route('orgs.show', $membership->organization) }}"
                            @if ($isCurrent) aria-current="true" @endif
                            @class([
                                'block px-3 py-2 text-sm hover:bg-zinc-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-zinc-800',
                                'font-semibold' => $isCurrent,
                            ])
                        >
                            {{ $membership->organization->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </details>
@endif

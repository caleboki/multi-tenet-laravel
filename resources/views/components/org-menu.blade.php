@props([
    'organization',
    'joinRequestCount',
])

@php
    $links = [
        ['label' => 'Home', 'route' => 'orgs.show', 'current' => ['orgs.show']],
        ['label' => 'Roster', 'route' => 'orgs.members.index', 'current' => ['orgs.members.*']],
        ['label' => 'Invite volunteer', 'route' => 'orgs.invitations.create', 'current' => ['orgs.invitations.*']],
        ['label' => "Join requests ({$joinRequestCount})", 'route' => 'orgs.join-requests.index', 'current' => ['orgs.join-requests.*']],
        ['label' => 'Import', 'route' => 'orgs.imports.create', 'current' => ['orgs.imports.*']],
        ['label' => 'Settings', 'route' => 'orgs.settings.edit', 'current' => ['orgs.settings.*', 'orgs.signup-link.*']],
    ];
@endphp

<nav aria-label="{{ $organization->name }} administration" class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <ul class="mx-auto flex max-w-5xl flex-wrap gap-x-1 px-2 sm:px-4">
        @foreach ($links as $link)
            @php($isCurrent = request()->routeIs(...$link['current']))

            <li>
                <a href="{{ route($link['route'], $organization) }}"@if ($isCurrent) aria-current="page"@endif @class([
                    'inline-flex border-b-2 px-2 py-3 text-sm font-medium focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600',
                    'border-indigo-600 text-indigo-700 dark:border-indigo-400 dark:text-indigo-300' => $isCurrent,
                    'border-transparent text-zinc-700 hover:border-zinc-300 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100' => ! $isCurrent,
                ])>
                    {{ $link['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>

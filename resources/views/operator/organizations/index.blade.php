@extends('layouts.app')

@section('title', 'Organizations')

@section('content')
    <h1 class="text-2xl font-semibold">Organizations</h1>

    <nav aria-label="Organization status">
        <ul class="flex flex-wrap gap-2">
            @foreach (\App\Enums\OrganizationStatus::cases() as $tab)
                <li>
                    <a
                        href="{{ route('operator.organizations.index', ['status' => $tab->value]) }}"
                        @if ($tab === $status) aria-current="page" @endif
                        @class([
                            'inline-flex rounded-md px-3 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                            'bg-indigo-600 text-white' => $tab === $status,
                            'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100' => $tab !== $status,
                        ])
                    >
                        {{ $tab->label() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($organizations->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-400">There are no {{ Str::lower($status->label()) }} organizations.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                <caption class="sr-only">{{ $status->label() }} organizations</caption>
                <thead class="bg-zinc-50 dark:bg-zinc-950">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Name</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Contact email</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Requested by</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Created</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Active members</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($organizations as $organization)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('operator.organizations.show', $organization) }}" class="text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                                    {{ $organization->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-status-badge>
                            </td>
                            <td class="px-4 py-3">{{ $organization->contact_email }}</td>
                            <td class="px-4 py-3">
                                {{ $organization->requester->name }}
                                <span class="block text-xs text-zinc-600 dark:text-zinc-400">{{ $organization->requester->email }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $organization->created_at->format('j M Y') }}</td>
                            <td class="px-4 py-3">{{ $organization->active_members_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $organizations->links() }}
    @endif
@endsection

@extends('layouts.app')

@section('title', 'Roster')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Roster</h1>

        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('orgs.members.export', [$organization, ...array_filter(['q' => $search, 'role' => $role?->value, 'status' => $status?->value])]) }}" class="rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Export (current filters)
            </a>
            <a href="{{ route('orgs.imports.create', $organization) }}" class="rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Import
            </a>
            <a href="{{ route('orgs.invitations.create', $organization) }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Invite volunteer
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('orgs.members.index', $organization) }}" role="search" aria-label="Search the roster" class="flex flex-wrap items-end gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="min-w-48 flex-1">
            <x-input name="q" label="Name or email" type="search" :value="$search" />
        </div>

        <x-select name="role" label="Role" :options="\App\Enums\MembershipRole::options()" :value="$role?->value" placeholder="Any role" />
        <x-select name="status" label="Status" :options="\App\Enums\RosterStatus::options()" :value="$status?->value" placeholder="Any status" />

        <div class="flex items-center gap-3">
            <x-button>Search</x-button>
            <a href="{{ route('orgs.members.index', $organization) }}" class="rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Clear
            </a>
        </div>
    </form>

    @if ($entries->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-400">No one in the roster matches.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                <caption class="sr-only">Members and open invitations of {{ $organization->name }}</caption>
                <thead class="bg-zinc-50 dark:bg-zinc-950">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Name</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Date joined</th>
                        <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                @if ($entry->kind === 'membership')
                                    <a href="{{ route('orgs.members.show', [$organization, $entry->key]) }}" class="text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                                        {{ $entry->name }}
                                    </a>
                                @else
                                    {{ $entry->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $entry->email }}</td>
                            <td class="px-4 py-3">{{ $entry->role->label() }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-status-badge>
                            </td>
                            <td class="px-4 py-3">{{ $entry->joined_at?->format('j M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($entry->kind === 'invitation')
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('orgs.invitations.resend', [$organization, $entry->key]) }}">
                                            @csrf
                                            <x-button variant="secondary">
                                                Resend<span class="sr-only"> invitation to {{ $entry->name }}</span>
                                            </x-button>
                                        </form>
                                        <form method="POST" action="{{ route('orgs.invitations.destroy', [$organization, $entry->key]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="secondary">
                                                Cancel<span class="sr-only"> invitation to {{ $entry->name }}</span>
                                            </x-button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $entries->links() }}
    @endif
@endsection

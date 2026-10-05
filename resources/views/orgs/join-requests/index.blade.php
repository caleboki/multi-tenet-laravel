@extends('layouts.app')

@section('title', 'Join requests')

@section('content')
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">Join requests</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            People who asked to join {{ $organization->name }} through its sign-up link. Approved people join as volunteers.
        </p>
    </div>

    @if ($joinRequests->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-400">No one is waiting for approval.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                <caption class="sr-only">Join requests waiting for approval</caption>
                <thead class="bg-zinc-50 dark:bg-zinc-950">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Name</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Requested</th>
                        <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($joinRequests as $joinRequest)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $joinRequest->user->name }}</td>
                            <td class="px-4 py-3">{{ $joinRequest->user->email }}</td>
                            <td class="px-4 py-3">{{ $joinRequest->requested_at?->format('j M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <form method="POST" action="{{ route('orgs.join-requests.approve', [$organization, $joinRequest]) }}">
                                        @csrf
                                        <x-button>
                                            Approve<span class="sr-only"> {{ $joinRequest->user->name }}</span>
                                        </x-button>
                                    </form>
                                    <form method="POST" action="{{ route('orgs.join-requests.destroy', [$organization, $joinRequest]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="secondary">
                                            Decline<span class="sr-only"> {{ $joinRequest->user->name }}</span>
                                        </x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $joinRequests->links() }}
    @endif
@endsection

@extends('layouts.app')

@section('title', $membership->user->name)

@section('content')
    <a href="{{ route('orgs.members.index', $organization) }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to the roster
    </a>

    <h1 class="text-2xl font-semibold">{{ $membership->user->name }}</h1>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 rounded-lg border border-zinc-200 bg-white p-4 text-sm sm:grid-cols-[max-content_1fr] dark:border-zinc-800 dark:bg-zinc-900">
        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Name</dt>
        <dd>{{ $membership->user->name }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Email</dt>
        <dd>{{ $membership->user->email }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Phone</dt>
        <dd>{{ $membership->user->phone ?? 'Not provided' }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Role</dt>
        <dd>{{ $membership->role->label() }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Status</dt>
        <dd>
            @php($rosterStatus = \App\Enums\RosterStatus::fromMembership($membership->status))
            <x-status-badge :tone="$rosterStatus->tone()">{{ $rosterStatus->label() }}</x-status-badge>
        </dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Date joined</dt>
        <dd>{{ $membership->joined_at?->format('j M Y') ?? '—' }}</dd>
    </dl>

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        Members keep their own name, email and phone number up to date. You can't change them here.
    </p>

    <section aria-labelledby="manage-heading" class="flex max-w-xl flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="manage-heading" class="text-lg font-semibold">Role and access</h2>

        <x-error-alert field="membership" />

        @if (in_array($membership->status, [\App\Enums\MembershipStatus::Active, \App\Enums\MembershipStatus::Inactive], true))
            <form method="POST" action="{{ route('orgs.members.update', [$organization, $membership]) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PATCH')

                <x-select name="role" label="Role" :options="\App\Enums\MembershipRole::options()" :value="$membership->role->value" />
                <x-button variant="secondary">Change role</x-button>
            </form>

            <form method="POST" action="{{ route('orgs.members.update', [$organization, $membership]) }}" class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                @csrf
                @method('PATCH')

                @if ($membership->isActive())
                    <input type="hidden" name="status" value="{{ \App\Enums\MembershipStatus::Inactive->value }}">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Deactivating ends this person's access. They stay in the roster as inactive, and you can reactivate them later.</p>
                    <x-button variant="danger" class="self-start">Deactivate</x-button>
                @else
                    <input type="hidden" name="status" value="{{ \App\Enums\MembershipStatus::Active->value }}">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Reactivating gives this person their access back.</p>
                    <x-button class="self-start">Reactivate</x-button>
                @endif

                @error('status')
                    <p class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror
            </form>
        @elseif ($membership->status === \App\Enums\MembershipStatus::Pending)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                This person has asked to join. Review the request on the
                <a href="{{ route('orgs.join-requests.index', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">join requests page</a>.
            </p>
        @else
            <p class="text-sm text-zinc-600 dark:text-zinc-400">This person has left the organization. They can ask to join again, or you can invite them.</p>
        @endif
    </section>
@endsection

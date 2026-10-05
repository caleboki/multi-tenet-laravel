@extends('layouts.app')

@section('title', $organization->name)

@section('content')
    <a href="{{ route('operator.organizations.index', ['status' => $organization->status->value]) }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to {{ Str::lower($organization->status->label()) }} organizations
    </a>

    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ $organization->name }}</h1>
        <x-status-badge :tone="$organization->status->tone()">{{ $organization->status->label() }}</x-status-badge>
    </div>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 rounded-lg border border-zinc-200 bg-white p-4 text-sm sm:grid-cols-[max-content_1fr] dark:border-zinc-800 dark:bg-zinc-900">
        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Contact email</dt>
        <dd>{{ $organization->contact_email }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Requested by</dt>
        <dd>{{ $organization->requester->name }} ({{ $organization->requester->email }})</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Created</dt>
        <dd>{{ $organization->created_at->format('j M Y') }}</dd>

        <dt class="font-medium text-zinc-600 dark:text-zinc-400">Active members</dt>
        <dd>{{ $organization->active_members_count }}</dd>

        @if ($organization->status === \App\Enums\OrganizationStatus::Rejected)
            <dt class="font-medium text-zinc-600 dark:text-zinc-400">Reason for rejection</dt>
            <dd>{{ $organization->rejection_reason }}</dd>
        @endif
    </dl>

    @switch ($organization->status)
        @case (\App\Enums\OrganizationStatus::Pending)
            <div class="flex max-w-xl flex-col gap-6 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <form method="POST" action="{{ route('operator.organizations.approve', $organization) }}" class="flex flex-col gap-2">
                    @csrf
                    <p class="text-sm">Approving makes the organization active and its requester the first administrator.</p>
                    <x-button class="self-start">Approve</x-button>
                </form>

                <form method="POST" action="{{ route('operator.organizations.reject', $organization) }}" class="flex flex-col gap-2 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                    @csrf
                    <label for="reason" class="text-sm font-medium">Reason for rejecting</label>
                    <textarea
                        id="reason"
                        name="reason"
                        rows="3"
                        maxlength="1000"
                        required
                        @error('reason') aria-invalid="true" aria-describedby="reason-error" @else aria-describedby="reason-hint" @enderror
                        @class([
                            'rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-indigo-600 dark:bg-zinc-950',
                            'border-zinc-300 dark:border-zinc-700' => ! $errors->has('reason'),
                            'border-red-600 dark:border-red-500' => $errors->has('reason'),
                        ])
                    >{{ old('reason') }}</textarea>
                    <p id="reason-hint" class="text-xs text-zinc-600 dark:text-zinc-400">The requester receives this reason by email.</p>
                    @error('reason')
                        <p id="reason-error" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <x-button variant="danger" class="self-start">Reject</x-button>
                </form>
            </div>
            @break

        @case (\App\Enums\OrganizationStatus::Active)
            <form method="POST" action="{{ route('operator.organizations.suspend', $organization) }}" class="flex max-w-xl flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @csrf
                <p class="text-sm">Suspending stops every member from opening the organization until it's reinstated. Its active administrators are told by email.</p>
                <x-button variant="danger" class="self-start">Suspend</x-button>
            </form>
            @break

        @case (\App\Enums\OrganizationStatus::Suspended)
            <form method="POST" action="{{ route('operator.organizations.reinstate', $organization) }}" class="flex max-w-xl flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                @csrf
                <p class="text-sm">Reinstating gives the organization's members their access back.</p>
                <x-button class="self-start">Reinstate</x-button>
            </form>
            @break
    @endswitch
@endsection

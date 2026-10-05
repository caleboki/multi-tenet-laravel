@extends('layouts.app')

@section('title', 'Invite a volunteer')

@section('content')
    <a href="{{ route('orgs.members.index', $organization) }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to the roster
    </a>

    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">Invite a volunteer</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            We'll email them a link to join {{ $organization->name }}. The link works for 7 days.
        </p>
    </div>

    <form method="POST" action="{{ route('orgs.invitations.store', $organization) }}" class="flex max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        @csrf

        <x-input name="name" label="Name" autocomplete="off" required />
        <x-input name="email" label="Email" type="email" autocomplete="off" required />

        @error('email')
            <a href="{{ route('orgs.members.index', [$organization, 'q' => old('email')]) }}" class="-mt-2 self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
                Find them in the roster
            </a>
        @enderror

        <x-select name="role" label="Role" :options="\App\Enums\MembershipRole::options()" :value="\App\Enums\MembershipRole::Volunteer->value" required />

        <x-button class="self-start">Send invitation</x-button>
    </form>
@endsection

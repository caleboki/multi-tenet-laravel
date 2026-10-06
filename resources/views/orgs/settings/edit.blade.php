@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="text-2xl font-semibold">Settings</h1>

    <section aria-labelledby="details-heading" class="flex max-w-2xl flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="details-heading" class="text-lg font-semibold">Organization details</h2>

        <form method="POST" action="{{ route('orgs.settings.update', $organization) }}" class="flex flex-col gap-4">
            @csrf
            @method('PATCH')

            <x-input name="name" label="Name" :value="$organization->name" maxlength="120" required />
            <x-input name="contact_email" label="Contact email" type="email" :value="$organization->contact_email" required />

            <div class="flex flex-col gap-1">
                <input type="hidden" name="self_signup_enabled" value="0">
                <label class="flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="self_signup_enabled"
                        value="1"
                        @checked(old('self_signup_enabled', $organization->self_signup_enabled))
                        aria-describedby="self_signup_enabled-hint"
                        class="mt-0.5 size-4 rounded border-zinc-300 focus-visible:outline-2 focus-visible:outline-indigo-600 dark:border-zinc-700"
                    >
                    Accept join requests through the sign-up link
                </label>
                <p id="self_signup_enabled-hint" class="text-xs text-zinc-600 dark:text-zinc-400">When this is off, the link tells people the organization isn't accepting sign-ups.</p>
            </div>

            <x-button class="self-start">Save settings</x-button>
        </form>
    </section>

    <section aria-labelledby="sign-up-link-heading" class="flex max-w-2xl flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="sign-up-link-heading" class="text-lg font-semibold">Sign-up link</h2>

        @unless ($organization->self_signup_enabled)
            <p class="text-sm text-amber-900 dark:text-amber-200">Sign-ups are turned off, so this link isn't accepting requests.</p>
        @endunless

        <x-input
            name="signup_link"
            label="Share this link with people who want to volunteer"
            :value="route('join.show', $organization->signup_token)"
            hint="People who use it ask to join, and an administrator approves each request."
            readonly
            onfocus="this.select()"
        />

        <div class="flex flex-wrap items-center gap-3">
            <x-button type="button" variant="secondary" data-copy-target="signup_link" hidden>Copy link</x-button>
            <span role="status" data-copy-status class="text-sm text-zinc-600 dark:text-zinc-400"></span>
        </div>

        <form method="POST" action="{{ route('orgs.signup-link.store', $organization) }}" class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            @csrf
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Creating a new link stops the current one working straight away.</p>
            <x-button variant="secondary" class="self-start">Create a new link</x-button>
        </form>
    </section>
@endsection

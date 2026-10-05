@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="text-2xl font-semibold">Settings</h1>

    <section aria-labelledby="sign-up-link-heading" class="flex max-w-2xl flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 id="sign-up-link-heading" class="text-lg font-semibold">Sign-up link</h2>

        <x-input
            name="signup_link"
            label="Share this link with people who want to volunteer"
            :value="route('join.show', $organization->signup_token)"
            hint="People who use it ask to join, and an administrator approves each request."
            readonly
            onfocus="this.select()"
        />
    </section>
@endsection

@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <h1 class="text-xl font-semibold">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input name="email" label="Email" type="email" :value="$request->query('email')" autocomplete="email" required />
        <x-input name="password" label="New password" type="password" autocomplete="new-password" required />
        <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />

        <x-button>Save new password</x-button>
    </form>
@endsection

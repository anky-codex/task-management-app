@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="card" style="max-width: 380px; margin: 3rem auto 0;">
        <h1>Log in</h1>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn" style="width: 100%;">Log in</button>
        </form>

        <p class="muted" style="margin-top: 1.25rem; font-size: 0.85rem;">
            Seeded accounts (password for all: <code>password</code>):<br>
            manager&#64;example.com &middot; alice&#64;example.com &middot; bob&#64;example.com
        </p>
    </div>
@endsection

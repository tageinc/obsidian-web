@extends('layouts.app')

@section('content')
<div class="container">
    <a href="{{ route('developer-workspace', ['section' => 'api-keys']) }}">Developer Workspace</a>
    <h1 class="h3 mt-3">External API Keys</h1>
    <p>Keys grant developer access to the external API. Each key belongs to the configured developer.</p>
    @if (session('success'))
        <p role="status" class="alert alert-success">{{ session('success') }}</p>
    @endif
    @if ($plainTextKey)
        <div class="alert alert-success">
            <label for="new-api-key">Copy this key now. It will not be shown again.</label>
            <input id="new-api-key" class="form-control" readonly value="{{ $plainTextKey }}" autocomplete="off">
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('developer.api-keys.store') }}" class="mb-4">
        @csrf
        <div class="mb-3">
            <label for="key-name">Key name</label>
            <input id="key-name" name="name" class="form-control" required maxlength="100" value="{{ old('name') }}">
        </div>
        <div class="mb-3">
            <label for="key-expiration">Expires on (UTC, optional)</label>
            <input id="key-expiration" name="expires_at" type="date" class="form-control" value="{{ old('expires_at') }}">
        </div>
        <button class="btn btn-primary" type="submit">Create Key</button>
    </form>
    <div class="table-responsive" role="region" aria-label="External API keys" tabindex="0">
        <table class="table">
            <thead><tr><th>Name</th><th>Prefix</th><th>Status</th><th>Last used</th><th>Expires</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($keys['data'] as $key)
                    <tr>
                        <th scope="row">{{ $key['name'] }}</th><td>{{ $key['prefix'] }}…</td><td>{{ $key['status'] }}</td>
                        <td>{{ $key['last_used_at'] ?? 'Never' }}</td><td>{{ $key['expires_at'] ?? 'Never' }}</td>
                        <td>
                            @if ($key['status'] !== 'Revoked')
                                <form method="POST" action="{{ route('developer.api-keys.revoke', $key['id']) }}">
                                    @csrf
                                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Revoke {{ $key['name'] }}">Revoke</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No API keys have been created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <nav aria-label="API key pages">
        @if ($keys['current_page'] > 1)
            <a href="{{ route('developer.api-keys.index', ['page' => $keys['current_page'] - 1]) }}">Previous</a>
        @endif
        @if ($keys['current_page'] < $keys['last_page'])
            <a href="{{ route('developer.api-keys.index', ['page' => $keys['current_page'] + 1]) }}">Next</a>
        @endif
    </nav>
</div>
@endsection

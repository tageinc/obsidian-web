<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Expired — Obsidian</title>
    @include('frontend.theme-bootstrap')
    <link href="{{ asset('css/accent.css') }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--obsidian-page);
            color: var(--obsidian-text);
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1rem;
        }
        .card {
            background-color: var(--obsidian-surface);
            border: 1px solid var(--obsidian-border);
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.4); max-width: 420px; width: 100%;
            padding: 2.5rem 2rem; text-align: center;
        }
        .card h1 { font-size: 1.35rem; margin-bottom: .75rem; }
        .card p { color: var(--obsidian-text-muted); line-height: 1.6; margin-bottom: 1.5rem; }
        .timer { font-size: 2.5rem; font-weight: 700; color: var(--obsidian-success); margin-bottom: 1rem; }
        .btn-group { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn, .btn-primary {
            display: inline-block; padding: .7rem 2rem;
            background-color: var(--obsidian-accent);
            color: var(--obsidian-accent-ink);
            border: 1px solid var(--obsidian-accent);
            border-radius: 8px;
            font-size: 1rem; cursor: pointer; text-decoration: none; transition: background .2s, color .2s, border-color .2s;
        }
        .btn:hover, .btn-primary:hover {
            background-color: var(--obsidian-accent-hover);
            color: var(--obsidian-accent-ink);
            border-color: var(--obsidian-accent-hover);
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-secondary {
            display: inline-block; padding: .7rem 2rem;
            background-color: var(--obsidian-surface-subtle);
            color: var(--obsidian-text);
            border: none;
            border-radius: 8px;
            font-size: 1rem; cursor: pointer; text-decoration: none; transition: background .2s;
        }
        .btn-secondary:hover { background-color: var(--obsidian-surface-hover); }
        .status { margin-top: 1rem; font-size: .9rem; color: var(--obsidian-text-muted); min-height: 1.2em; }
    </style>
</head>
<body>
<div class="card">
    <h1>Your session has expired</h1>
    <p id="msg">{{ $message ?? 'For security, please log in again.' }}</p>
    <div class="timer" id="countdown">{{ $countdown }}</div>

    <div class="btn-group">
        <!-- Primary: try to refresh session (works if user is still authed) -->
        <button class="btn btn-primary" id="refresh-btn">Refresh Session</button>
        <!-- Fallback: go straight to login -->
        <a href="{{ route('login') }}" class="btn-secondary" id="cta">Log in now</a>
    </div>

    <p class="status" id="status"></p>
</div>

<script>
(function () {
    var countdown  = {{ $countdown }};
    var el         = document.getElementById('countdown');
    var msg        = document.getElementById('msg');
    var csrfEl     = document.querySelector('meta[name="csrf-token"]');
    var refreshBtn = document.getElementById('refresh-btn');
    var statusEl   = document.getElementById('status');

    if (!csrfEl) { statusEl.textContent = 'Security error. Logging in…'; window.location.href = '{{ route("login") }}'; return; }
    var csrfToken = csrfEl.getAttribute('content');

    refreshBtn.addEventListener('click', function () {
        clearInterval(countdownTimer);
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Refreshing…';
        statusEl.textContent = '';
        fetch('{{ route("session.expire.refresh") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Request failed: ' + res.status);
                return res.json();
            })
            .then(function () {
                statusEl.textContent = 'Session restored. Reloading…';
                setTimeout(function () { window.location.href = '{{ route("dashboard") }}'; }, 800);
            })
            .catch(function () {
                statusEl.textContent = 'Connection error. Logging in…';
                refreshBtn.disabled = true;
                window.location.href = '{{ route("login") }}';
            });
    });

    // Countdown timer (auto-redirect if user does nothing)
    var countdownTimer = setInterval(function () {
        countdown--;
        if (countdown <= 0) {
            clearInterval(countdownTimer);
            msg.textContent = 'Redirecting to login…';
            refreshBtn.disabled = true;
            window.location.href = '{{ route("login") }}';
            return;
        }
        el.textContent = countdown + 's';
    }, 1000);
})();
</script>
</body>
</html>

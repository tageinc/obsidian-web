<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Expired — Obsidian</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e; color: #eee;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; padding: 1rem;
        }
        .card {
            background: #16213e; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.4); max-width: 420px; width: 100%;
            padding: 2.5rem 2rem; text-align: center;
        }
        .card h1 { font-size: 1.35rem; margin-bottom: .75rem; }
        .card p { color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; }
        .timer { font-size: 2.5rem; font-weight: 700; color: #f59e0b; margin-bottom: 1rem; }
        .btn-group { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: .7rem 2rem;
            background: #4361ee; color: #fff; border: none; border-radius: 8px;
            font-size: 1rem; cursor: pointer; text-decoration: none; transition: background .2s;
        }
        .btn:hover { background: #3a56d4; }
        .btn:active { transform: scale(.97); }
        .btn-secondary {
            display: inline-block; padding: .7rem 2rem;
            background: #374151; color: #eee; border: none; border-radius: 8px;
            font-size: 1rem; cursor: pointer; text-decoration: none; transition: background .2s;
        }
        .btn-secondary:hover { background: #4b5563; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .status { margin-top: 1rem; font-size: .9rem; color: #94a3b8; min-height: 1.2em; }
    </style>
</head>
<body>
<div class="card">
    <h1>Your session has expired</h1>
    <p id="msg">{{ $message ?? 'For security, please log in again.' }}</p>
    <div class="timer" id="countdown">{{ $countdown }}</div>

    <div class="btn-group">
        <!-- Primary: try to refresh session (works if user is still authed) -->
        <button class="btn" id="refresh-btn">Refresh Session</button>
        <!-- Fallback: go straight to login -->
        <a href="{{ route('login') }}" class="btn-secondary" id="cta">Log in now</a>
    </div>

    <div class="status" id="status"></div>
</div>

<script>
(function () {
    var countdown  = {{ $countdown }};
    var el         = document.getElementById('countdown');
    var msg        = document.getElementById('msg');
    var refreshBtn = document.getElementById('refresh-btn');
    var statusEl   = document.getElementById('status');

    // Refresh button: XHR to the session/refresh endpoint.
    refreshBtn.addEventListener('click', function () {
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Refreshing…';
        fetch('{{ route("session.expire.refresh") }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                if (res.ok) return res.json();
                // Already unauthenticated — fall through to login page.
                statusEl.textContent = 'Please log in again.';
                window.location.href = document.getElementById('cta').href;
            })
            .then(function () {
                statusEl.textContent = 'Session restored. Reloading…';
                setTimeout(function () { location.reload(); }, 800);
            })
            .catch(function () {
                statusEl.textContent = 'Connection error. Logging in…';
                window.location.href = document.getElementById('cta').href;
            });
    });

    // Countdown timer (auto-redirect if user does nothing)
    var id = setInterval(function () {
        countdown--;
        if (countdown <= 0) {
            clearInterval(id);
            msg.textContent = 'Redirecting to login…';
            refreshBtn.disabled = true;
            window.location.href = document.getElementById('cta').href;
            return;
        }
        el.textContent = countdown + 's';
    }, 1000);
})();
</script>
</body>
</html>

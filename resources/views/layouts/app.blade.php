<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Task Management') &middot; {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f5f7;
            --card: #ffffff;
            --border: #e2e5ea;
            --text: #1f2430;
            --muted: #6b7280;
            --brand: #4f46e5;
            --brand-dark: #4338ca;
            --danger: #dc2626;
            --danger-bg: #fef2f2;
            --ok-bg: #ecfdf5;
            --ok-text: #047857;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        header.topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 0.9rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header.topbar a.brand { font-weight: 700; color: var(--text); text-decoration: none; font-size: 1.05rem; }
        header.topbar .who { color: var(--muted); font-size: 0.9rem; margin-right: 1rem; }
        header.topbar .role-badge {
            display: inline-block; padding: 0.1rem 0.5rem; border-radius: 999px;
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .02em;
            background: #eef2ff; color: var(--brand-dark); margin-left: 0.4rem;
        }
        main.container { max-width: 900px; margin: 2rem auto; padding: 0 1.25rem; }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 10px;
            padding: 1.5rem; margin-bottom: 1.25rem;
        }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        h2 { font-size: 1.1rem; margin: 0 0 0.75rem; }
        .flash-ok, .flash-error {
            padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.92rem;
        }
        .flash-ok { background: var(--ok-bg); color: var(--ok-text); }
        .flash-error { background: var(--danger-bg); color: var(--danger); }
        .flash-error ul { margin: 0.25rem 0 0; padding-left: 1.1rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #374151; }
        input, textarea, select {
            width: 100%; padding: 0.55rem 0.7rem; border: 1px solid var(--border); border-radius: 7px;
            font-size: 0.95rem; font-family: inherit; background: #fff; color: var(--text);
        }
        textarea { resize: vertical; min-height: 5rem; }
        .field { margin-bottom: 1rem; }
        .btn {
            display: inline-block; padding: 0.55rem 1.1rem; border-radius: 7px; border: none;
            background: var(--brand); color: #fff; font-weight: 600; font-size: 0.9rem; cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: var(--brand-dark); }
        .btn.secondary { background: #fff; color: var(--text); border: 1px solid var(--border); }
        .btn.danger { background: #fff; color: var(--danger); border: 1px solid #fecaca; }
        form.inline { display: inline; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--border); font-size: 0.92rem; }
        th { color: var(--muted); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .02em; }
        tr:last-child td { border-bottom: none; }
        a { color: var(--brand); }
        .status-pill {
            display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.76rem;
            font-weight: 600; background: #f3f4f6; color: #374151; white-space: nowrap;
        }
        .status-todo { background: #f3f4f6; color: #374151; }
        .status-in_progress { background: #fef3c7; color: #92400e; }
        .status-under_qa { background: #e0e7ff; color: #3730a3; }
        .status-deploy_to_live { background: #dbeafe; color: #1d4ed8; }
        .status-submitted { background: #d1fae5; color: #065f46; }
        .priority-high { color: var(--danger); font-weight: 600; }
        .priority-medium { color: #92400e; }
        .priority-low { color: var(--muted); }
        .overdue { color: var(--danger); font-weight: 600; }
        .muted { color: var(--muted); }
        .empty { color: var(--muted); font-style: italic; padding: 1rem 0; }
        .actions-row { margin-top: 1.25rem; display: flex; gap: 0.6rem; }
    </style>
</head>
<body>
@auth
    <header class="topbar">
        <a href="{{ route('dashboard') }}" class="brand">{{ config('app.name') }}</a>
        <div>
            <span class="who">
                {{ auth()->user()->name }}
                <span class="role-badge">{{ auth()->user()->role }}</span>
            </span>
            <form action="{{ route('logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn secondary">Log out</button>
            </form>
        </div>
    </header>
@endauth

<main class="container">
    @if (session('status'))
        <div class="flash-ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="flash-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>

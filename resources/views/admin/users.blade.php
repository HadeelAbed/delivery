<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin</title>
    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #64748b; --accent: #0891b2; --card: #ffffff; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--fg); margin: 0; }
        .sidebar { width: 260px; min-height: 100vh; background: #1e293b; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: 12px 16px; border-radius: 6px; margin: 4px 12px; }
        .sidebar a:hover, .sidebar a.active { background: var(--accent); color: white; }
        .content { margin-left: 260px; padding: 24px; }
        .page-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .filter-row { margin-bottom: 20px; }
        .filter-row select { padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--muted); }
        .btn { padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500; text-decoration: none; background: var(--accent); color: white; border: 0; cursor: pointer; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: var(--border); }
        .pagination { margin-top: 16px; }
    </style>
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="content">
        <h1 class="page-title">Users</h1>

        <div class="filter-row">
            <form method="GET" action="/admin/users" style="gap: 8px;">
                <select name="role">
                    <option value="">All Roles</option>
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->value }}" {{ ($filters['role'] ?? '') === $roleOption->value ? 'selected' : '' }}>{{ $roleOption->value }}</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}" {{ ($filters['status'] ?? '') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Registered</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td>{{ $user->role->value }}</td>
                            <td><span class="status-badge">{{ $user->status->value }}</span></td>
                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $users->links() }}</div>
    </div>
</body>
</html>

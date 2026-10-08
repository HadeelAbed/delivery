@extends('layouts.admin')

@section('title', 'Admin — Approvals')
@section('page')
<div class="card">
    <h2>Approval Queue</h2>

    <h3>Merchants ({{ $merchants->count() }})</h3>
    @if ($merchants->isEmpty())
        <p>No pending merchant registrations.</p>
    @else
        <table>
            <thead>
                <tr><th>Business</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($merchants as $merchant)
                    <tr>
                        <td>{{ $merchant->merchantProfile->business_name ?? '—' }}</td>
                        <td>{{ $merchant->name }}</td>
                        <td>{{ $merchant->email }}</td>
                        <td>{{ $merchant->phone }}</td>
                        <td>{{ $merchant->created_at->format('Y-m-d') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.approve', $merchant) }}" class="inline-form">
                                @csrf
                                <button type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reject', $merchant) }}" class="inline-form">
                                @csrf
                                <input type="text" name="reason" placeholder="Rejection reason" required class="inline-input">
                                <button type="submit" class="secondary">Reject</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Drivers ({{ $drivers->count() }})</h3>
    @if ($drivers->isEmpty())
        <p>No pending driver registrations.</p>
    @else
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Vehicle</th><th>Registered</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($drivers as $driver)
                    <tr>
                        <td>{{ $driver->name }}</td>
                        <td>{{ $driver->email }}</td>
                        <td>{{ $driver->phone }}</td>
                        <td>{{ $driver->driverProfile->vehicle_type ?? '—' }}</td>
                        <td>{{ $driver->created_at->format('Y-m-d') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.users.approve', $driver) }}" class="inline-form">
                                @csrf
                                <button type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reject', $driver) }}" class="inline-form">
                                @csrf
                                <input type="text" name="reason" placeholder="Rejection reason" required class="inline-input">
                                <button type="submit" class="secondary">Reject</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@overwrite

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $organization->name }} - Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #26488C;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #26488C;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-section h2 {
            color: #26488C;
            font-size: 16px;
            border-bottom: 2px solid #C4973B;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-item {
            display: table-cell;
            width: 33.33%;
            padding: 10px;
        }
        .info-item .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
        }
        .info-item .value {
            font-size: 14px;
            color: #333;
            margin-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background-color: #26488C;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-admin {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-student {
            background-color: #e5e7eb;
            color: #374151;
        }
        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $organization->name }}</h1>
        <p>Organization Report</p>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="info-section">
        <h2>Organization Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="label">Organization Type</div>
                <div class="value">{{ $organization->type }}</div>
            </div>
            <div class="info-item">
                <div class="label">Status</div>
                <div class="value">{{ $organization->status }}</div>
            </div>
            <div class="info-item">
                <div class="label">Total Members</div>
                <div class="value">{{ $organization->users->count() }}</div>
            </div>
        </div>
        <div class="info-grid" style="margin-top: 10px;">
            <div class="info-item">
                <div class="label">Created Date</div>
                <div class="value">{{ $organization->created_at->format('F d, Y') }}</div>
            </div>
            <div class="info-item">
                <div class="label">Admins</div>
                <div class="value">{{ $organization->users->where('role', 'admin')->count() }}</div>
            </div>
            <div class="info-item">
                <div class="label">Students</div>
                <div class="value">{{ $organization->users->where('role', 'student')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2>Members List</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Email</th>
                    <th>Organization Role</th>
                    <th>User Role</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($organization->users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->full_name }}</td>
                    <td>{{ $user->student_id ?? 'N/A' }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ \App\Models\OrganizationUser::formatDisplayLabel($user->pivot->role ?? 'Member') }}</td>
                    <td>
                        <span class="badge badge-{{ $user->role === 'admin' ? 'admin' : 'student' }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $user->pivot->membership_status === 'Active' ? 'active' : 'inactive' }}">
                            {{ $user->pivot->membership_status }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>This is an official document generated by the UniOrg Management System</p>
        <p>&copy; {{ date('Y') }} OSAS - All Rights Reserved</p>
    </div>
</body>
</html>

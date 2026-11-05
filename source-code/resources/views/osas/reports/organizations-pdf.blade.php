<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Organizations Report</title>
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
        .stats {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .stat-box {
            display: table-cell;
            padding: 15px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .stat-box .label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #26488C;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        .badge-academic {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-non-academic {
            background-color: #f3e8ff;
            color: #7c3aed;
        }
        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-inactive {
            background-color: #f3f4f6;
            color: #4b5563;
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
        <h1>Organizations Report</h1>
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
        <p>OSAS - Office of Student Affairs and Services</p>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="label">Total</div>
            <div class="value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="label">Mother Org</div>
            <div class="value">{{ $stats['mother_org'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="label">Sub Org</div>
            <div class="value">{{ $stats['sub_org'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="label">Non-Academic</div>
            <div class="value">{{ $stats['non_academic'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="label">Active</div>
            <div class="value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="stat-box">
            <div class="label">Total Members</div>
            <div class="value">{{ $stats['total_members'] ?? 0 }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Organization Name</th>
                <th>Type</th>
                <th>Members</th>
                <th>Status</th>
                <th>Created Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($organizations as $index => $org)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $org->name }}</td>
                <td>
                    <span class="badge badge-{{ $org->type === 'Non-Academic' ? 'non-academic' : 'academic' }}">
                        {{ $org->type }}
                    </span>
                </td>
                <td>{{ $org->users_count ?? 0 }}</td>
                <td>
                    <span class="badge badge-{{ $org->status === 'Approved' ? 'active' : 'inactive' }}">
                        {{ $org->status }}
                    </span>
                </td>
                <td>{{ $org->created_at?->format('M d, Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This is an official document generated by the UniOrg Management System</p>
        <p>&copy; {{ date('Y') }} OSAS - All Rights Reserved</p>
    </div>
</body>
</html>

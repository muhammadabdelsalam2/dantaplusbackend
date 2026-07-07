<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            color: #222;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 6px;
        }

        h2 {
            font-size: 16px;
            margin: 22px 0 8px;
        }

        .meta {
            color: #555;
            margin-bottom: 18px;
        }

        table {
            border-collapse: collapse;
            margin-top: 8px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d7d7d7;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f2f4f7;
        }

        .profit {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Profit &amp; Loss Report</h1>
    <div class="meta">
        Clinic: {{ $clinic?->name ?? 'Clinic' }}<br>
        Period: {{ $from }} to {{ $to }}<br>
        Grouped by: {{ $groupBy }}
    </div>

    <h2>Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Revenue</th>
                <th>Expenses</th>
                <th>Profit</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format((float) ($summary['revenue'] ?? 0), 2) }}</td>
                <td>{{ number_format((float) ($summary['expenses'] ?? 0), 2) }}</td>
                <td class="profit">{{ number_format((float) ($summary['profit'] ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Series</h2>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Revenue</th>
                <th>Expenses</th>
                <th>Profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($series as $row)
                <tr>
                    <td>{{ $row['period'] ?? '' }}</td>
                    <td>{{ number_format((float) ($row['revenue'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($row['expenses'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($row['profit'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No data available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

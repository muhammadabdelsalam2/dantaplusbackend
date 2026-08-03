<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Insurance Approval</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 15px; margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #6b7280; }
        .grid { width: 100%; margin-top: 12px; }
        .grid td { border: 0; padding: 3px 0; }
    </style>
</head>
<body>
    <h1>Insurance Approval</h1>
    <div class="muted">{{ $approval->clinic?->name }}</div>

    <table class="grid">
        <tr><td><strong>Approval Code</strong></td><td>{{ $approval->code }}</td></tr>
        <tr><td><strong>Approval Number / Ref ID</strong></td><td>{{ $approval->approval_number ?? $approval->ref_id }}</td></tr>
        <tr><td><strong>Patient</strong></td><td>{{ $approval->patient?->user?->name }}</td></tr>
        <tr><td><strong>Insurance Company</strong></td><td>{{ $approval->company?->name }}</td></tr>
        <tr><td><strong>Status</strong></td><td>{{ $approval->status }}</td></tr>
        <tr><td><strong>Date</strong></td><td>{{ optional($approval->date)->toDateString() }}</td></tr>
        <tr><td><strong>Expiry Date</strong></td><td>{{ optional($approval->expiry_date)->toDateString() }}</td></tr>
        <tr><td><strong>Coverage</strong></td><td>{{ number_format((float) $approval->coverage_percent, 2) }}%</td></tr>
        <tr><td><strong>Approved Amount</strong></td><td>{{ number_format((float) $approval->approved_amount, 2) }}</td></tr>
        <tr><td><strong>Used Amount</strong></td><td>{{ number_format((float) $approval->used_amount, 2) }}</td></tr>
    </table>

    <h2>Services Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Code</th>
                <th>Amount</th>
                <th>Co-pay</th>
                <th>Tooth #</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($approval->services as $service)
                <tr>
                    <td>{{ $service->service_name }}</td>
                    <td>{{ $service->service_code }}</td>
                    <td>{{ number_format((float) $service->amount, 2) }}</td>
                    <td>{{ number_format((float) $service->co_pay, 2) }}</td>
                    <td>{{ $service->tooth_number }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No services recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

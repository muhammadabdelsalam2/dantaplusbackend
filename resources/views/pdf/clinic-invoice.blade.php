<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Invoice</title>
</head>
<body>
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <p>Clinic: {{ $invoice->clinic?->name ?? 'Clinic' }}</p>
    <p>Patient: {{ $invoice->patient?->user?->name ?? ('Patient #' . $invoice->patient_id) }}</p>
    <p>Doctor: {{ $invoice->doctor?->name ?? '-' }}</p>
    <p>Issued at: {{ optional($invoice->issued_at)->toDateString() }}</p>
    <p>Due date: {{ optional($invoice->due_date)->toDateString() }}</p>
    <p>Total: {{ number_format((float) $invoice->total, 2) }}</p>
    <p>Paid: {{ number_format((float) $invoice->paid, 2) }}</p>
    <p>Remaining: {{ number_format((float) $invoice->remaining, 2) }}</p>
    <p>Status: {{ $invoice->status }}</p>
</body>
</html>

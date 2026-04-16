<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
</head>
<body>
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <p>Clinic: {{ $invoice->clinic?->name ?? 'External / Not linked' }}</p>
    <p>Issue date: {{ optional($invoice->issue_date)->toDateString() }}</p>
    <p>Due date: {{ optional($invoice->due_date)->toDateString() }}</p>
    <p>Total: {{ number_format((float) $invoice->total_amount, 2) }}</p>
    <p>Status: {{ $invoice->status }}</p>
</body>
</html>

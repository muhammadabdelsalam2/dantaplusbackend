<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 40px;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.45;
        }
        .header { overflow: hidden; padding-bottom: 28px; border-bottom: 1px solid #e5e7eb; }
        .left { float: left; width: 52%; }
        .right { float: right; width: 44%; text-align: right; }
        .company-name { font-size: 26px; font-weight: 700; }
        .invoice-title { font-size: 32px; color: #6b7280; font-weight: 400; }
        .muted { color: #6b7280; }
        .bill-to { margin: 54px 0 36px; }
        .section-label { color: #6b7280; font-weight: 700; letter-spacing: .4px; }
        .bill-name { font-size: 20px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 700; }
        td { padding: 14px 12px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .summary { width: 49%; float: right; margin-top: 52px; }
        .summary td { border: 0; border-top: 1px solid #e5e7eb; padding: 20px 0; font-size: 20px; font-weight: 700; }
        .footer { clear: both; margin-top: 90px; padding-top: 30px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $clinic = $invoice->clinic;
    $description = $invoice->order?->items?->pluck('item_name')->filter()->join(', ') ?: ($invoice->order?->order_code ? 'Order ' . $invoice->order->order_code : 'Dental services');
@endphp

<div class="header">
    <div class="left">
        <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
        <div class="muted">
            {{ $company?->address }}<br>
            {{ $company?->phone }}
            @if($company?->email)
                | {{ $company->email }}
            @endif
        </div>
    </div>

    <div class="right">
        <div class="invoice-title">INVOICE</div>
        <div><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
        <div><strong>Date:</strong> {{ optional($invoice->issue_date)->format('m/d/Y') }}</div>
    </div>
</div>

<div class="bill-to">
    <div class="section-label">BILL TO</div>
    <div class="bill-name">{{ $clinic?->name ?? 'External Clinic' }}</div>
    <div>{{ $clinic?->address }}</div>
    <div>{{ $clinic?->phone }}</div>
</div>

<table>
    <thead>
        <tr>
            <th>Description</th>
            <th class="num" style="width: 24%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $description }}</td>
            <td class="num">${{ number_format((float) $invoice->total_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="summary">
    <table>
        <tr>
            <td>Total Due:</td>
            <td class="num">${{ number_format((float) $invoice->total_amount, 2) }}</td>
        </tr>
    </table>
</div>

<div class="footer">
    <div>Thank you for your visit!</div>
    @if($invoice->due_date)
        <div>Payment is due by {{ $invoice->due_date->format('m/d/Y') }}.</div>
    @endif
</div>
</body>
</html>

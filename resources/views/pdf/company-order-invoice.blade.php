<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 42px;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.45;
        }
        .header { overflow: hidden; padding-bottom: 28px; border-bottom: 1px solid #e5e7eb; }
        .brand { float: left; width: 58%; }
        .brand-logo { float: left; width: 64px; height: 64px; object-fit: cover; margin-right: 16px; }
        .brand-name { font-size: 24px; font-weight: 700; margin-top: 4px; }
        .muted { color: #6b7280; }
        .invoice-meta { float: right; width: 38%; text-align: right; }
        .invoice-title { font-size: 30px; color: #6b7280; font-weight: 400; margin-bottom: 4px; }
        .bill-to { margin: 34px 0; }
        .section-label { font-size: 12px; font-weight: 700; color: #6b7280; letter-spacing: .4px; }
        .bill-name { font-size: 17px; font-weight: 700; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; padding: 10px 8px; text-align: left; font-weight: 700; }
        td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; }
        .num { text-align: right; white-space: nowrap; }
        .summary { width: 50%; float: right; margin-top: 28px; }
        .summary td { border: 0; padding: 6px 0; }
        .summary .rule td { border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .summary .total td { font-size: 16px; font-weight: 700; }
        .footer { clear: both; margin-top: 80px; padding-top: 22px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
@php
    $subtotal = (float) $order->items->sum('line_total');
    $tax = $order->invoice ? (float) $order->invoice->tax : round($subtotal * 0.14, 2);
    $shipping = (float) ($order->shipping_cost ?? 0);
    $total = $subtotal + $tax + $shipping;
    $clinicName = $order->clinic?->name ?? $order->external_clinic_name ?? 'External Clinic';
    $clinicPhone = $order->clinic?->phone ?? $order->external_clinic_phone;
@endphp

<div class="header">
    <div class="brand">
        @if($order->company?->logo_url)
            <img class="brand-logo" src="{{ $order->company->logo_url }}" alt="Logo">
        @endif
        <div class="brand-name">{{ $order->company?->name ?? 'Company' }}</div>
        <div class="muted">
            {{ $order->company?->address }}<br>
            {{ $order->company?->phone }}
            @if($order->company?->email)
                | {{ $order->company->email }}
            @endif
        </div>
    </div>

    <div class="invoice-meta">
        <div class="invoice-title">INVOICE</div>
        <div><strong>Invoice #:</strong> {{ $invoiceNumber }}</div>
        <div><strong>Date:</strong> {{ optional($order->order_date ?? $order->created_at)->format('d/m/Y') }}</div>
        <div><strong>Order ID:</strong> {{ $order->order_code }}</div>
    </div>
</div>

<div class="bill-to">
    <div class="section-label">BILL TO</div>
    <div class="bill-name">{{ $clinicName }}</div>
    <div>{{ $order->clinic?->address ?? $order->delivery_address }}</div>
    <div>{{ $clinicPhone }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th>Item Name</th>
            <th class="num" style="width: 16%;">Quantity</th>
            <th class="num" style="width: 16%;">Unit Price</th>
            <th class="num" style="width: 16%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->item_name ?: $item->product?->name }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">${{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="num"><strong>${{ number_format((float) $item->line_total, 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <table>
        <tr>
            <td>Subtotal:</td>
            <td class="num">${{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>Tax (14%):</td>
            <td class="num">${{ number_format($tax, 2) }}</td>
        </tr>
        <tr>
            <td>Shipping:</td>
            <td class="num">${{ number_format($shipping, 2) }}</td>
        </tr>
        <tr class="rule total">
            <td>Total Amount Due:</td>
            <td class="num">${{ number_format($total, 2) }}</td>
        </tr>
    </table>
</div>

<div class="footer">
    <div>Thank you for your business!</div>
    <div>Payment is due within 30 days. Please make payments to the account specified.</div>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 38px 42px;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.45;
        }
        table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 1px solid #e5e7eb; margin-bottom: 32px; }
        .header-table td { border: 0; vertical-align: top; padding: 0 0 26px; }
        .brand-logo { width: 64px; height: 64px; object-fit: cover; display: block; }
        .brand-name { font-size: 24px; font-weight: 700; margin-bottom: 2px; }
        .muted { color: #6b7280; }
        .invoice-title { font-size: 31px; color: #6b7280; font-weight: 400; margin-bottom: 4px; }
        .invoice-meta { text-align: right; white-space: nowrap; }
        .bill-to { margin-bottom: 32px; }
        .section-label { font-size: 12px; font-weight: 700; color: #6b7280; letter-spacing: .4px; }
        .bill-name { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .items th { background: #f3f4f6; padding: 10px 8px; text-align: left; font-weight: 700; border: 0; }
        .items td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; direction: ltr; unicode-bidi: embed; }
        .summary { width: 48%; margin-left: auto; margin-top: 28px; }
        .summary td { border: 0; padding: 6px 0; }
        .summary .rule td { border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .summary .total td { font-size: 16px; font-weight: 700; }
        .footer { margin-top: 74px; padding-top: 22px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
        .rtl-cell { direction: rtl; unicode-bidi: embed; text-align: right; }
        .ltr-cell { direction: ltr; unicode-bidi: embed; text-align: left; }
    </style>
</head>
<body>
@php
    use App\Support\PdfText;

    $subtotal = (float) $order->items->sum('line_total');
    $tax = $order->invoice ? (float) $order->invoice->tax : round($subtotal * 0.14, 2);
    $shipping = (float) ($order->shipping_cost ?? 0);
    $total = $subtotal + $tax + $shipping;
    $company = $order->company;
    $clinicName = $order->clinic?->name ?? $order->external_clinic_name ?? 'External Clinic';
    $clinicAddress = $order->clinic?->address ?? $order->delivery_address;
    $clinicPhone = $order->clinic?->phone ?? $order->external_clinic_phone;
    $companyContact = PdfText::oneLine([$company?->phone, $company?->email]);
@endphp

<table class="header-table">
    <tr>
        <td style="width: 10%;">
            @if($company?->logo_url)
                <img class="brand-logo" src="{{ $company->logo_url }}" alt="Logo">
            @endif
        </td>
        <td style="width: 54%; padding-left: 14px;">
            <div class="brand-name">{!! PdfText::span($company?->name ?? 'Company') !!}</div>
            <div class="muted">{!! PdfText::span($company?->address) !!}</div>
            <div class="muted">{!! PdfText::span($companyContact) !!}</div>
        </td>
        <td class="invoice-meta" style="width: 36%;">
            <div class="invoice-title">INVOICE</div>
            <div><strong>Invoice #:</strong> {{ $invoiceNumber }}</div>
            <div><strong>Date:</strong> {{ optional($order->order_date ?? $order->created_at)->format('d/m/Y') }}</div>
            <div><strong>Order ID:</strong> {{ $order->order_code }}</div>
        </td>
    </tr>
</table>

<div class="bill-to">
    <div class="section-label">BILL TO</div>
    <div class="bill-name">{!! PdfText::span($clinicName) !!}</div>
    <div>{!! PdfText::span($clinicAddress) !!}</div>
    <div>{!! PdfText::span($clinicPhone) !!}</div>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th>Item Name</th>
            <th class="num" style="width: 15%;">Quantity</th>
            <th class="num" style="width: 16%;">Unit Price</th>
            <th class="num" style="width: 16%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            @php($itemName = $item->item_name ?: $item->product?->name)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="{{ PdfText::hasArabic($itemName) ? 'rtl-cell' : 'ltr-cell' }}">{!! PdfText::span($itemName) !!}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ PdfText::money($item->unit_price) }}</td>
                <td class="num"><strong>{{ PdfText::money($item->line_total) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="summary">
    <tr>
        <td>Subtotal:</td>
        <td class="num">{{ PdfText::money($subtotal) }}</td>
    </tr>
    <tr>
        <td>Tax (14%):</td>
        <td class="num">{{ PdfText::money($tax) }}</td>
    </tr>
    <tr>
        <td>Shipping:</td>
        <td class="num">{{ PdfText::money($shipping) }}</td>
    </tr>
    <tr class="rule total">
        <td>Total Amount Due:</td>
        <td class="num">{{ PdfText::money($total) }}</td>
    </tr>
</table>

<div class="footer">
    <div>Thank you for your business!</div>
    <div>Payment is due within 30 days. Please make payments to the account specified.</div>
</div>
</body>
</html>

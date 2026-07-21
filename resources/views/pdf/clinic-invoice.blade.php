<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice</title>

<style>
    *{
        margin:0;
        padding:0;
        box-sizing:border-box;
    }

    body{
        font-family: DejaVu Sans, sans-serif;
        color:#333;
        padding:40px;
        font-size:14px;
    }

    .header{
        width:100%;
        overflow:hidden;
        margin-bottom:30px;
    }

    .left{
        float:left;
        width:50%;
    }

    .right{
        float:right;
        width:40%;
        text-align:right;
    }

    h1{
        font-size:42px;
        color:#777;
        font-weight:300;
        margin-bottom:10px;
    }

    h2{
        font-size:20px;
        margin-bottom:8px;
    }

    hr{
        border:none;
        border-top:1px solid #ddd;
        margin:25px 0;
    }

    .section-title{
        color:#777;
        font-size:13px;
        font-weight:bold;
        margin-bottom:8px;
    }

    .patient{
        margin-bottom:35px;
        line-height:1.8;
    }

    table{
        width:100%;
        border-collapse:collapse;
    }

    thead{
        background:#f3f4f6;
    }

    th{
        text-align:left;
        padding:12px;
        font-size:15px;
    }

    td{
        padding:14px 12px;
        border-bottom:1px solid #e5e5e5;
    }

    th:last-child,
    td:last-child{
        text-align:right;
    }

    .summary{
        width:40%;
        float:right;
        margin-top:35px;
    }

    .summary table td{
        border:none;
        padding:8px 0;
    }

    .summary .total{
        font-size:24px;
        font-weight:bold;
    }

    .footer{
        clear:both;
        text-align:center;
        color:#777;
        margin-top:90px;
        border-top:1px solid #ddd;
        padding-top:30px;
    }
</style>

</head>
<body>

<div class="header">

    <div class="left">
        <h2>{{ $invoice->clinic?->name ?? 'Clinic Name' }}</h2>

        <div>
            {{ $invoice->clinic?->address ?? '' }}<br>
            {{ $invoice->clinic?->phone ?? '' }}
            @if($invoice->clinic?->email)
                |
                {{ $invoice->clinic->email }}
            @endif
        </div>

    </div>

    <div class="right">

        <h1>INVOICE</h1>

        <div>
            <strong>Invoice #:</strong>
            {{ $invoice->invoice_number }}
        </div>

        <div style="margin-top:6px;">
            <strong>Date:</strong>
            {{ optional($invoice->issued_at)->format('m/d/Y') }}
        </div>

    </div>

</div>

<hr>

<div class="patient">

    <div class="section-title">
        BILL TO
    </div>

    <h2>
        {{ $invoice->patient?->user?->name }}
    </h2>

    <div>
        {{ $invoice->patient?->address ?? '' }}
    </div>

    <div>
        {{ $invoice->patient?->phone ?? '' }}
    </div>

</div>

<table>

    <thead>

        <tr>
            <th>Description</th>
            <th>Amount</th>
        </tr>

    </thead>

    <tbody>

        <tr>

            <td>
                {{ $invoice->description ?? 'Dental Treatment' }}
            </td>

            <td>
                ${{ number_format($invoice->total,2) }}
            </td>

        </tr>

    </tbody>

</table>

<div class="summary">

<table>

<tr>
    <td>Total</td>
    <td style="text-align:right;">
        ${{ number_format($invoice->total,2) }}
    </td>
</tr>

<tr>
    <td>Paid</td>
    <td style="text-align:right;">
        ${{ number_format($invoice->paid,2) }}
    </td>
</tr>

<tr>
    <td><strong>Remaining</strong></td>
    <td style="text-align:right;">
        <strong>${{ number_format($invoice->remaining,2) }}</strong>
    </td>
</tr>

<tr class="total">
    <td>Total Due</td>
    <td style="text-align:right;">
        <strong>${{ number_format($invoice->remaining,2) }}</strong>
    </td>
</tr>

</table>

</div>

<div class="footer">

    <div>Thank you for your visit!</div>

    @if($invoice->due_date)
        <div style="margin-top:6px;">
            Payment is due by {{ $invoice->due_date->format('m/d/Y') }}.
        </div>
    @endif

</div>

</body>
</html>

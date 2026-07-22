<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Order {{ $labOrder['case_number'] }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #172033; font-size: 12px; background: #ffffff; }
        .shell { border: 1px solid #d9e2ef; border-radius: 12px; padding: 18px; }
        .top { width: 100%; border-bottom: 3px solid #2f80ed; padding-bottom: 14px; margin-bottom: 16px; }
        .top td { vertical-align: top; }
        .logo { width: 58px; height: 58px; border-radius: 12px; background: #eaf3ff; text-align: center; line-height: 58px; color: #2f80ed; font-weight: 700; font-size: 20px; }
        .lab-name { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .muted { color: #667085; }
        .qr { width: 76px; height: 76px; border: 2px solid #172033; margin-left: auto; font-size: 8px; text-align: center; line-height: 76px; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .card { border: 1px solid #d9e2ef; border-radius: 10px; padding: 12px; background: #fbfdff; }
        .two td { width: 50%; vertical-align: top; padding-right: 8px; }
        .title { color: #2f80ed; font-weight: 800; font-size: 14px; margin-bottom: 8px; }
        .row { margin: 4px 0; }
        .label { color: #667085; display: inline-block; min-width: 92px; }
        .details { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .details th { background: #eef6ff; color: #184f90; text-align: left; }
        .details th, .details td { border: 1px solid #d9e2ef; padding: 9px; }
        .teeth { text-align: center; margin-top: 10px; }
        .tooth { display: inline-block; width: 28px; height: 30px; border: 1px solid #9db7d6; border-radius: 50% 50% 45% 45%; margin: 2px; line-height: 30px; font-size: 9px; color: #47607a; }
        .selected { background: #2f80ed; color: #fff; border-color: #1d63c2; font-weight: 800; }
        .notes { min-height: 54px; border: 1px solid #d9e2ef; border-radius: 10px; padding: 10px; background: #fff; }
        .signatures { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-top: 14px; }
        .sig { height: 62px; border-bottom: 2px solid #172033; text-align: center; vertical-align: bottom; color: #667085; }
    </style>
</head>
<body>
@php
    $selectedTeeth = collect((array) ($labOrder['case']['teeth'] ?? []))->map(fn ($v) => (string) $v)->all();
    $teeth = array_merge(range(18, 11), range(21, 28), range(48, 41), range(31, 38));
@endphp
<div class="shell">
    <table class="top">
        <tr>
            <td style="width:70px;"><div class="logo">LAB</div></td>
            <td>
                <div class="lab-name">{{ $labOrder['lab']['name'] ?? 'Dental Lab' }}</div>
                <div class="muted">{{ $labOrder['lab']['address'] ?? '' }}</div>
                <div class="row"><span class="label">Lab Order</span><strong>{{ $labOrder['case_number'] }}</strong></div>
            </td>
            <td style="width:90px; text-align:right;"><div class="qr">QR</div></td>
        </tr>
    </table>

    <table class="cards two">
        <tr>
            <td><div class="card">
                <div class="title">Clinic & Doctor</div>
                <div class="row"><span class="label">Clinic</span>{{ $labOrder['clinic']['name'] ?? '' }}</div>
                <div class="row"><span class="label">Doctor</span>{{ $labOrder['clinic']['doctor_name'] ?? '' }}</div>
                <div class="row"><span class="label">Phone</span>{{ $labOrder['clinic']['phone'] ?? '' }}</div>
                <div class="row"><span class="label">Address</span>{{ $labOrder['clinic']['address'] ?? '' }}</div>
            </div></td>
            <td><div class="card">
                <div class="title">Patient</div>
                <div class="row"><span class="label">Name</span>{{ $labOrder['patient']['name'] ?? '' }}</div>
                <div class="row"><span class="label">File #</span>{{ $labOrder['patient']['file_number'] ?? '' }}</div>
                <div class="row"><span class="label">Age</span>{{ $labOrder['patient']['age'] ?? '' }}</div>
                <div class="row"><span class="label">Gender</span>{{ $labOrder['patient']['gender'] ?? '' }}</div>
            </div></td>
        </tr>
    </table>

    <div class="title">Case Details</div>
    <table class="details">
        <tr>
            <th>Case Type</th><th>Material</th><th>Shade</th><th>Delivery Date</th><th>Priority</th>
        </tr>
        <tr>
            <td>{{ $labOrder['case']['case_type'] ?? '' }}</td>
            <td>{{ $labOrder['case']['material'] ?? '' }}</td>
            <td>{{ $labOrder['case']['shade'] ?? '' }}</td>
            <td>{{ $labOrder['case']['delivery_date'] ?? '' }}</td>
            <td>{{ $labOrder['case']['priority'] ?? '' }}</td>
        </tr>
    </table>

    <div class="title" style="margin-top:14px;">Teeth Diagram</div>
    <div class="teeth">
        @foreach ($teeth as $tooth)
            <span class="tooth {{ in_array((string) $tooth, $selectedTeeth, true) ? 'selected' : '' }}">{{ $tooth }}</span>
            @if ($loop->iteration === 16)<br>@endif
        @endforeach
    </div>

    <table class="cards two">
        <tr>
            <td><div class="card">
                <div class="title">Clinic Notes</div>
                <div class="notes">{{ $labOrder['case']['clinic_notes'] ?? '' }}</div>
            </div></td>
            <td><div class="card">
                <div class="title">Lab Use</div>
                <div class="row"><span class="label">Technician</span>{{ $labOrder['lab_use']['assigned_technician']['name'] ?? '' }}</div>
                <div class="row"><span class="label">Assigned</span>{{ $labOrder['lab_use']['assignment_date'] ?? '' }}</div>
            </div></td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td class="sig">Clinic Signature</td>
            <td class="sig">Lab Receiver Signature</td>
            <td class="sig">Technician Signature</td>
        </tr>
    </table>
</div>
</body>
</html>

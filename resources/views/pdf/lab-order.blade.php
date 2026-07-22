<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Order {{ $labOrder['case_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { font-size: 24px; font-weight: 700; color: #0f172a; }
        .muted { color: #64748b; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .grid td { width: 50%; vertical-align: top; padding: 8px; border: 1px solid #e5e7eb; }
        .section-title { font-size: 15px; font-weight: 700; color: #2563eb; margin: 16px 0 8px; }
        .rows { width: 100%; border-collapse: collapse; }
        .rows th, .rows td { text-align: left; padding: 8px; border: 1px solid #e5e7eb; }
        .rows th { background: #f8fafc; width: 28%; }
        .signatures td { height: 58px; vertical-align: bottom; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $labOrder['lab']['name'] ?? 'Dental Lab' }}</div>
        <div class="muted">{{ $labOrder['lab']['address'] ?? '' }}</div>
        <div>Lab Order: <strong>{{ $labOrder['case_number'] }}</strong></div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Clinic</strong><br>
                {{ $labOrder['clinic']['name'] ?? '' }}<br>
                {{ $labOrder['clinic']['address'] ?? '' }}<br>
                {{ $labOrder['clinic']['phone'] ?? '' }}<br>
                Doctor: {{ $labOrder['clinic']['doctor_name'] ?? '' }}
            </td>
            <td>
                <strong>Patient</strong><br>
                {{ $labOrder['patient']['name'] ?? '' }}<br>
                File #: {{ $labOrder['patient']['file_number'] ?? '' }}<br>
                Age: {{ $labOrder['patient']['age'] ?? '' }}<br>
                Gender: {{ $labOrder['patient']['gender'] ?? '' }}
            </td>
        </tr>
    </table>

    <div class="section-title">Case Details</div>
    <table class="rows">
        <tr><th>Case Type</th><td>{{ $labOrder['case']['case_type'] ?? '' }}</td></tr>
        <tr><th>Material</th><td>{{ $labOrder['case']['material'] ?? '' }}</td></tr>
        <tr><th>Shade</th><td>{{ $labOrder['case']['shade'] ?? '' }}</td></tr>
        <tr><th>Delivery Date</th><td>{{ $labOrder['case']['delivery_date'] ?? '' }}</td></tr>
        <tr><th>Priority</th><td>{{ $labOrder['case']['priority'] ?? '' }}</td></tr>
        <tr><th>Teeth</th><td>{{ implode(', ', (array) ($labOrder['case']['teeth'] ?? [])) }}</td></tr>
        <tr><th>Clinic Notes</th><td>{{ $labOrder['case']['clinic_notes'] ?? '' }}</td></tr>
    </table>

    <div class="section-title">Lab Use</div>
    <table class="rows">
        <tr><th>Assigned Technician</th><td>{{ $labOrder['lab_use']['assigned_technician']['name'] ?? '' }}</td></tr>
        <tr><th>Assignment Date</th><td>{{ $labOrder['lab_use']['assignment_date'] ?? '' }}</td></tr>
    </table>

    <div class="section-title">Signatures</div>
    <table class="rows signatures">
        <tr>
            <td>Clinic Signature</td>
            <td>Lab Receiver Signature</td>
            <td>Technician Signature</td>
        </tr>
    </table>
</body>
</html>

<!doctype html>
<html>
<body>
<p>Hello {{ $recipientName }},</p>

<p>Your Denta+ account has been created.</p>

<p>
    System link: <a href="{{ $systemLink }}">{{ $systemLink }}</a><br>
    @if($dashboardLink)
        Dashboard link: <a href="{{ $dashboardLink }}">{{ $dashboardLink }}</a><br>
    @endif
    Email: {{ $email }}<br>
    Password: {{ $plainPassword }}
</p>

@if($subscription)
    <p>
        Subscription plan: {{ $subscription['plan'] ?? '' }}<br>
        Max users: {{ $subscription['max_users'] ?? '' }}
    </p>
@endif

<p>Please sign in and change your password after your first login.</p>
</body>
</html>

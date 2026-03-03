<?php

namespace App\Enums;

enum OtpType: string
{
    // Registration OTP
    case REGISTER = 'register';

    // Login OTP
    case LOGIN = 'login';

    // Reset password OTP
    case RESET_PASSWORD = 'reset_password';

    // Change password OTP
    case CHANGE_PASSWORD = 'change_password';
}
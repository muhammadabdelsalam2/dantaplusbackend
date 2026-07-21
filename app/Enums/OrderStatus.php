<?php

namespace App\Enums;

class OrderStatus
{
    public const PENDING_SUPPLIER_CONFIRMATION = 'Pending Supplier Confirmation';
    public const ACCEPTED   = 'Accepted';
    public const PROCESSING = 'Processing';
    public const SHIPPED    = 'Shipped';
    public const DELIVERED  = 'Delivered';
    public const COMPLETED  = 'Completed';
    public const CANCELLED  = 'Cancelled';
    public const REJECTED   = 'Rejected';

    public const ALL = [
        self::PENDING_SUPPLIER_CONFIRMATION,
        self::ACCEPTED,
        self::PROCESSING,
        self::SHIPPED,
        self::DELIVERED,
        self::COMPLETED,
        self::CANCELLED,
        self::REJECTED,
    ];
}

<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kpis' => $this['kpis'] ?? [],
            'top_clinic' => $this['top_clinic'] ?? null,
            'order_trends' => $this['order_trends'] ?? [],
            'low_stock_alerts' => $this['low_stock_alerts'] ?? [],
        ];
    }
}

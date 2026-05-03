<?php

namespace App\Observers;

use App\Models\MaterialProduct;

class MaterialProductObserver
{
    /**
     * Handle the MaterialProduct "created" event.
     */
 
public function created(MaterialProduct $product): void
{
    if (empty($product->barcode)) {
        $product->updateQuietly([
            'barcode' => '200' . str_pad($product->id, 4, '0', STR_PAD_LEFT) . strtoupper(\Illuminate\Support\Str::random(6)),
        ]);
    }
}

    /**
     * Handle the MaterialProduct "updated" event.
     */
    public function updated(MaterialProduct $materialProduct): void
    {
        //
    }

    /**
     * Handle the MaterialProduct "deleted" event.
     */
    public function deleted(MaterialProduct $materialProduct): void
    {
        //
    }

    /**
     * Handle the MaterialProduct "restored" event.
     */
    public function restored(MaterialProduct $materialProduct): void
    {
        //
    }

    /**
     * Handle the MaterialProduct "force deleted" event.
     */
    public function forceDeleted(MaterialProduct $materialProduct): void
    {
        //
    }
}

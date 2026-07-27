<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // The old edit_gurad migration change was execution-safety only.
        // No production data is changed here by design.
    }

    public function down(): void
    {
        // No-op: this migration intentionally does not mutate schema or data.
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicTaskReply extends Model
{
    protected $fillable = ['clinic_task_id', 'created_by', 'message'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ClinicTask::class, 'clinic_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

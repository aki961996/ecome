<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProcessingJob extends Model
{
    use HasFactory;

     protected $fillable = [
        'order_id',
        'job_type',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'j_job_id'  , // larvel q j id
        'laravel_job_id'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now()
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now()
        ]);
    }
}

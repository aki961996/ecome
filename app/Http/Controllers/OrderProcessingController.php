<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProcessingJob;
use App\Jobs\ProcessOrderJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderProcessingController extends Controller
{
    

    public function processOrder(Request $request, $orderId): JsonResponse
{
    try {
        DB::beginTransaction();

        
        $order = Order::where('id', $orderId)
             ->whereIn('status', ['pending', 'processing'])
            ->lockForUpdate() 
            ->firstOrFail();

      
        $existingJob = OrderProcessingJob::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($existingJob) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already being processed',
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ], 409);
        }

       

        // Dispatch 
        $job = ProcessOrderJob::dispatch($order)->delay(now()->addSeconds(2));

         // Create a pending job record // record
        $jobRecord = OrderProcessingJob::create([
            'order_id' => $order->id,
            'job_type' => 'order_processing',
            'status' => 'pending',
            'started_at' => now(),
            'laravel_job_id' => $job->id ?? null
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Order processing started',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'processing_job_id' => $jobRecord->id,

                 'laravel_job_id' => $job->id ?? null,
                'queue' => config('queue.default'),
                'estimated_completion' => 'Processing in background'
            ]
        ], 202);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'Order not found or not in pending status',
            'error' => 'Order not available for processing'
        ], 404);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Failed to dispatch order processing job', [
            'order_id' => $orderId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to start order processing',
            'error' => $e->getMessage()
        ], 500);
    }
}

   

}
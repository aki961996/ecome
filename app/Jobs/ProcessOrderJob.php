<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\OrderProcessingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;
    public $tries = 3;
    public $timeout = 120;
    public $maxExceptions = 2;
    public $backoff = [5, 10, 15]; 

    public function __construct(Order $order)
    {
        $this->order = $order->withoutRelations();
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $jobRecord = null;

        try {
            DB::beginTransaction();

            
            $jobRecord = OrderProcessingJob::firstOrCreate(
                [
                    'order_id' => $this->order->id,
                    'job_type' => 'order_processing',
                ],
                [
                    'status' => 'pending',
                    'started_at' => now(),
                ]
            );

           
            $jobRecord->markAsProcessing();

           
            $this->order->update(['status' => 'processing']);

            //log
            
            Log::info("Order processing started", [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'job_id' => $jobRecord->id,
                'attempt' => $this->attempts()
            ]);

            // Validate fresh data
            $this->validateProductAvailability();

            
            $this->updateProductStock();

            // Calculate final order total
            $finalTotal = $this->calculateFinalOrderTotal();

            // Update order with final total
            $this->order->update([
                'total_amount' => $finalTotal,
                'status' => 'completed',
                'processed_at' => now()
            ]);

            // Mark job 
            $jobRecord->markAsCompleted();

            DB::commit();

            // Log processing time
            $processingTime = round(microtime(true) - $startTime, 3);
            Log::info("Order processing completed successfully", [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'processing_time_seconds' => $processingTime,
                'final_total' => $finalTotal,
                'job_id' => $jobRecord->id,
                'attempt' => $this->attempts()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            
            if (str_contains($e->getMessage(), 'Stock quantity changed') && $this->attempts() < $this->tries) {
                Log::warning("Stock conflict detected, retrying job", [
                    'order_id' => $this->order->id,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'error' => $e->getMessage()
                ]);
                
                
                $this->release($this->backoff[$this->attempts() - 1] ?? 5);
                return;
            }

            //  "failed" if no more retries  // no 
            $this->order->update(['status' => 'failed']);

            // Update job record with error
            if ($jobRecord) {
                $jobRecord->markAsFailed($e->getMessage());
            } else {
                OrderProcessingJob::create([
                    'order_id' => $this->order->id,
                    'job_type' => 'order_processing',
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'started_at' => now(),
                    'completed_at' => now()
                ]);
            }

            // Log error 
            $processingTime = round(microtime(true) - $startTime, 3);
            Log::error("Order processing failed after all retries", [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'error' => $e->getMessage(),
                'processing_time_seconds' => $processingTime,
                'attempt' => $this->attempts(),
                'job_id' => $jobRecord?->id
            ]);

            throw $e;
        }
    }

   
    private function validateProductAvailability(): void
    {
        $orderItems = OrderItem::where('order_id', $this->order->id)->get();

        $insufficientStock = [];

        foreach ($orderItems as $item) {
           
            $currentStock = Product::where('id', $item->product_id)->value('stock_quantity');
            $productName = Product::where('id', $item->product_id)->value('name');
            
            if ($currentStock < $item->quantity) {
                $insufficientStock[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $productName,
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => $currentStock
                ];
            }
        }

        if (!empty($insufficientStock)) {
            throw new \Exception(
                "Insufficient stock for products: " . 
                collect($insufficientStock)
                    ->pluck('product_name')
                    ->implode(', ')
            );
        }
    }

  
    private function updateProductStock(): void
    {
        $orderItems = OrderItem::where('order_id', $this->order->id)->get();

        foreach ($orderItems as $item) {
           
            $product = Product::find($item->product_id);
            
            if (!$product) {
                throw new \Exception("Product not found: {$item->product_id}");
            }

            $currentStock = $product->stock_quantity;
            $newStock = $currentStock - $item->quantity;
            
            if ($newStock < 0) {
                throw new \Exception(
                    "Insufficient stock for product: {$product->name}. Available: {$currentStock}, Requested: {$item->quantity}"
                );
            }

           
            $updated = DB::table('products')
                ->where('id', $product->id)
                ->where('stock_quantity', $currentStock) 
                ->update([
                    'stock_quantity' => $newStock,
                    'updated_at' => now()
                ]);

            if (!$updated) {
                
                $actualStock = Product::where('id', $product->id)->value('stock_quantity');
                throw new \Exception(
                    "Stock quantity changed for product: {$product->name}. " .
                    "Expected: {$currentStock}, Current: {$actualStock}. Please retry."
                );
            }

            Log::info("Product stock updated successfully", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'old_stock' => $currentStock,
                'new_stock' => $newStock,
                'quantity_sold' => $item->quantity,
                'attempt' => $this->attempts()
            ]);
        }
    }

   
    private function calculateFinalOrderTotal(): float
    {
        return OrderItem::where('order_id', $this->order->id)
            ->sum('total_price');
    }

    public function failed(\Throwable $exception): void
    {

        //log
        Log::emergency("Order processing job failed after all retries", [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_class' => __CLASS__
        ]);

       
        $this->order->update(['status' => 'failed']);

       
        $existingJob = OrderProcessingJob::where('order_id', $this->order->id)
            ->where('job_type', 'order_processing')
            ->latest()
            ->first();

        if ($existingJob) {
            $existingJob->markAsFailed($exception->getMessage());
        } else {
            OrderProcessingJob::create([
                'order_id' => $this->order->id,
                'job_type' => 'order_processing',
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'started_at' => now(),
                'completed_at' => now()
            ]);
        }
    }
}
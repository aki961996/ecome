<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;

class SetupTestOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:setup-order';
   

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup a test order for processing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         // Create test data
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $category = Category::firstOrCreate(
            ['name' => 'Test Electronics'],
            ['description' => 'Test category']
        );

        $product1 = Product::firstOrCreate(
            ['name' => 'Test Product 1'],
            [
                'price' => 100.00,
                'stock_quantity' => 50,
                'category_id' => $category->id
            ]
        );

        $product2 = Product::firstOrCreate(
            ['name' => 'Test Product 2'],
            [
                'price' => 50.00,
                'stock_quantity' => 30,
                'category_id' => $category->id
            ]
        );

        // Create pending order
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'TEST-' . time(),
            'total_amount' => 0,
            'status' => 'pending'
        ]);

        // Add order items
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit_price' => $product1->price,
            'total_price' => 2 * $product1->price
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => $product2->price,
            'total_price' => $product2->price
        ]);

        $order->update(['total_amount' => 250.00]);

        $this->info("Test order created successfully!");
        $this->info("Order ID: " . $order->id);
        $this->info("User ID: " . $user->id);
        $this->info("Product IDs: " . $product1->id . ", " . $product2->id);
        $this->info("Run: curl -X POST http://localhost:8000/api/orders/{$order->id}/process");

        return Command::SUCCESS;
    }
}

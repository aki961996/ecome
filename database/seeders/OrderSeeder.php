<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       



          
        $products = Product::factory(5)->create();
        $this->command->info('Created 10 products');

       
        $orders = Order::factory(5)->create();
        $this->command->info('Created 5 orders');

       
        foreach ($orders as $order) {
            $itemCount = rand(3, 5);
            $orderItems = [];

            for ($i = 0; $i < $itemCount; $i++) {
                $product = $products->random(); 
                $quantity = rand(1, 3);
                $unitPrice = $product->price;
                $totalPrice = $quantity * $unitPrice;

                $orderItems[] = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            }

           
            OrderItem::insert($orderItems);
        }

        $this->command->info('Attached order items to all orders');
         
    }
}

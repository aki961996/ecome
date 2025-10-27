<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function getOrdersSummary(Request $request){
       $dateRange = $request->get('date_range', 30); // Default to last 30 days
       
        $startDate = Carbon::now()->subDays($dateRange)->startOfDay();
       
        $endDate = Carbon::now()->endOfDay();

        //paginatin and optimized queries
        $orders = $this->getOrdersWithDetails($startDate, $endDate);
       
        
       
        
        // Get dashboard statistics
        $stats = $this->getDashboardStats($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'statistics' => $stats,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'days' => $dateRange
                ]
            ]
        ]);
    }
     private function getOrdersWithDetails(Carbon $startDate, Carbon $endDate)
    {
        
        return Order::query()
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.total_amount',
                'orders.status',
                'orders.created_at',
                'users.name as customer_name',
                DB::raw('COUNT(order_items.id) as items_count'),
            ])
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->with(['items.product.category']) // Eager load with specific relations
            ->groupBy(
                'orders.id',
                'orders.order_number',
                'orders.total_amount',
                'orders.status',
                'orders.created_at',
                'users.name'
            )
            ->orderBy('orders.created_at', 'desc')
            ->paginate(20)
            ->through(function ($order) {
                // Get unique category names from order items
                $categories = $order->items->flatMap(function ($item) {
                    return $item->product->category ? [$item->product->category->name] : [];
                })->unique()->values();
                
                return [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'items_count' => $order->items_count,
                    'category_names' => $categories->implode(', '),
                    'order_date' => $order->created_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $order->created_at->diffForHumans(),
                ];
            });
    }

     private function getDashboardStats(Carbon $startDate, Carbon $endDate): array
    {
        // Total revenue for the period
        $revenueStats = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->select([
                DB::raw('COALESCE(SUM(total_amount), 0) as total_revenue'),
                DB::raw('COALESCE(AVG(total_amount), 0) as average_order_value'),
                DB::raw('COUNT(*) as total_orders')
            ])
            ->first();

        // Most popular category by order count
        $popularCategory = Category::query()
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            ])
            ->join('products', 'categories.id', '=', 'products.category_id')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', 'completed')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('order_count', 'desc')
            ->first();

        // Pending orders count
        $pendingOrders = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'pending')
            ->count();

        // Orders by status
        $ordersByStatus = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        return [
            'total_revenue' => (float) $revenueStats->total_revenue,
            'average_order_value' => (float) $revenueStats->average_order_value,
            'total_orders' => (int) $revenueStats->total_orders,
            'pending_orders' => $pendingOrders,
            'most_popular_category' => $popularCategory ? [
                'name' => $popularCategory->name,
                'order_count' => $popularCategory->order_count
            ] : null,
            'orders_by_status' => $ordersByStatus,
        ];
    }


     public function getDashboardStatsOnly(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 30);
        $startDate = Carbon::now()->subDays($dateRange)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $stats = $this->getDashboardStats($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
      
}

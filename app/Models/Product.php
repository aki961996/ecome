<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

     protected $fillable = [
        'name',
        'price',
        'stock_quantity',
        'category_id'
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function decreaseStock($quantity)
    {
        $this->stock_quantity -= $quantity;
        $this->save();
    }

    public function increaseStock($quantity)
    {
        $this->stock_quantity += $quantity;
        $this->save();
    }
}

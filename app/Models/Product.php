<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'order_id',
        'reference',
        'designation',
        'sales_unit',
        'lot',
        'cartQuantity',
        'comment',
        'gross_price',
        'discount_1',
        'discount_2',
        'discount_3',
        'line_total_ht',
        'line_total_ttc',
    ];

    protected $casts = [
        'cartQuantity'   => 'integer',
        'gross_price'    => 'decimal:4',
        'discount_1'     => 'decimal:4',
        'discount_2'     => 'decimal:4',
        'discount_3'     => 'decimal:4',
        'line_total_ht'  => 'decimal:2',
        'line_total_ttc' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

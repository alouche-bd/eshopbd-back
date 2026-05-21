<?php

namespace App\Models;

use App\Constants\OrderExportStatus;
use App\Constants\OrderType;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_reference',
        'order_type',
        'client_code',
        'raison_sociale',
        'finalClientCode',
        'finalClient',
        'shippingAddress',
        'currency',
        'carrier_code',
        'billing_country_code',
        'billing_address',
        'delivery_address',
        'total_ht',
        'total_ttc',
        'discount_amount',
        'export_status',
        'exported_at',
        'sent_at',
        'sage_order_reference',
        'excel_filename',
        'export_error',
        'archived_at',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'delivery_address' => 'array',
        'total_ht' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'exported_at' => 'datetime',
        'sent_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'order_id');
    }

    public function scopeDistributor($query)
    {
        return $query->where('order_type', OrderType::DISTRIBUTOR);
    }

    public function scopeExportStatus($query, string $status)
    {
        return $query->where('export_status', $status);
    }

    public function scopeAwaitingSend($query)
    {
        return $query->where('export_status', OrderExportStatus::EXPORTED);
    }

    /**
     * Generate the CUSORDREF reference. Must be called after the order has
     * been persisted (uses the DB-assigned id).
     */
    public function generateCustomerReference(): string
    {
        $client = $this->client_code ?: $this->finalClientCode;
        return sprintf('ESHOP-%d-%s', $this->id, $client);
    }
}

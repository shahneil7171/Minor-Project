<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'house_number',
        'street_address',
        'city',
        'state',
        'pincode',
        'country',
        'additional_info',
        'is_default_shipping',
        'is_default_billing',
    ];

    protected $casts = [
        'is_default_shipping' => 'boolean',
        'is_default_billing' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the address.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get default shipping address
     */
    public function scopeDefaultShipping($query)
    {
        return $query->where('is_default_shipping', true);
    }

    /**
     * Scope to get default billing address
     */
    public function scopeDefaultBilling($query)
    {
        return $query->where('is_default_billing', true);
    }

    /**
     * Get full address string
     */
    public function getFullAddressAttribute()
    {
        return "{$this->house_number}, {$this->street_address}, {$this->city}, {$this->state} - {$this->pincode}, {$this->country}";
    }
}

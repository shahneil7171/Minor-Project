<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $table = 'product_options';

    protected $fillable = ['name', 'values', 'sort_order', 'status'];

    protected $casts = [
        'values' => 'array',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Human readable list of the option values, e.g. "Red, Blue, Green".
     */
    public function valuesLabel(): string
    {
        return implode(', ', (array) $this->values);
    }
}

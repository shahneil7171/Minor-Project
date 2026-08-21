<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $fillable = ['email', 'status', 'subscribed_at'];

    protected $casts = [
        'status' => 'boolean',
        'subscribed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

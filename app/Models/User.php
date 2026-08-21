<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'bio', 'profile_photo_path', 'account_type', 'status', 'user_group_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Account lifecycle statuses managed from the admin Customers page.
     */
    public const STATUSES = [
        'active',
        'inactive',
        'blocked',
    ];

    /**
     * Human-readable labels for each account status.
     */
    public const STATUS_LABELS = [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'blocked'  => 'Blocked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'last_login' => 'datetime',
        ];
    }

    /**
     * Get the user's addresses.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the user's default shipping address.
     */
    public function defaultShippingAddress()
    {
        return $this->hasOne(Address::class)->where('is_default_shipping', true);
    }

    /**
     * Get the user's default billing address.
     */
    public function defaultBillingAddress()
    {
        return $this->hasOne(Address::class)->where('is_default_billing', true);
    }

    /**
     * Products the user has saved to their wishlist.
     */
    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * Orders placed by the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Reviews submitted by the user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * The staff permission group this user belongs to (nullable for shoppers).
     */
    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    /**
     * Whether this account is a staff member (admin panel access).
     */
    public function isStaff(): bool
    {
        return in_array($this->account_type, ['admin', 'manager'], true);
    }

    /**
     * Whether the account holds a granular admin permission.
     *
     * Legacy admins without an assigned group keep full access; managers
     * fall back to read-only access until they are placed in a group.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isStaff()) {
            return false;
        }

        if ($this->account_type === 'admin' && ! $this->user_group_id) {
            return true;
        }

        $group = $this->group;

        if (! $group) {
            // Managers without a group may only view.
            return str_ends_with($permission, '.view');
        }

        return $group->grants($permission);
    }

    /**
     * Search customers by name, email and/or phone.
     */
    public function scopeSearch($query, string $term, string $field = 'all')
    {
        $like = '%' . trim($term) . '%';

        return match ($field) {
            'name'  => $query->where('name', 'like', $like),
            'email' => $query->where('email', 'like', $like),
            'phone' => $query->where('phone', 'like', $like),
            default => $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            }),
        };
    }

    /**
     * Whether the account is in good standing (may sign in).
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Whether this user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->account_type === 'admin';
    }

    /**
     * Human-readable label for the current account status.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Get the user's profile photo URL.
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            return asset('storage/' . $this->profile_photo_path);
        }
        // Use Gravatar as fallback, with a nice placeholder if no Gravatar
        $email = strtolower(trim($this->email));
        $hash = md5($email);
        return "https://www.gravatar.com/avatar/{$hash}?s=500&d=identicon";
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin()
    {
        $this->update(['last_login' => now()]);
    }
}

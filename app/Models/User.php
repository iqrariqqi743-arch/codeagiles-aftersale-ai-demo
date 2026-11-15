
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * A merchant can have multiple subscription records.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the latest subscription.
     */
    public function latestSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->latest('id')
            ->first();
    }

    /**
     * Check whether the merchant has an active subscription
     * or a valid free trial.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->get()
            ->contains(
                fn (Subscription $subscription) =>
                    $subscription->isActive()
            );
    }

    /**
     * Get the remaining free trial days.
     */
    public function trialDaysRemaining(): int
    {
        $subscription = $this->subscriptions()
            ->where('status', 'trialing')
            ->latest('id')
            ->first();

        return $subscription
            ? $subscription->trialDaysRemaining()
            : 0;
    }
}

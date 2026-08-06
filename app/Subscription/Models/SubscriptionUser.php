<?php

namespace App\Subscription\Models;

use App\Payments\Models\Payment;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id', 'user_id', 'status', 'start_date', 'expires_at',
    'accesses_used', 'activated_at', 'suspended_at', 'resume_at',
    'status_reason', 'activation_payment_id',
])]
class SubscriptionUser extends Pivot
{
    use LogsModelChanges;

    protected $table = 'subscription_user';

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activationPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'activation_payment_id');
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'expires_at' => 'datetime',
            'accesses_used' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'resume_at' => 'datetime',
        ];
    }
}

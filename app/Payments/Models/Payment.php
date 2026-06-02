<?php

namespace App\Payments\Models;

use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_name',
    'last_name',
    'payment_type_id',
    'model_type',
    'model_id',
    'amount',
    'paid_at',
    'admin_id',
])]
class Payment extends Model
{
    use HasFactory;
    use LogsModelChanges;

    public const TYPE_CASH = 1;
    public const TYPE_CARD = 2;
    public const TYPE_BANK_TRANSFER = 3;

    public const MODEL_TYPE_SUBSCRIPTION_USER = 'subscription_user';
    public const MODEL_TYPE_EVENT_OCCURRENCE_USER = 'event_occurrence_user';

    public const PAYMENT_TYPES = [
        self::TYPE_CASH => 'cash',
        self::TYPE_CARD => 'card',
        self::TYPE_BANK_TRANSFER => 'bank_transfer',
    ];

    public const MODEL_TYPES = [
        self::MODEL_TYPE_SUBSCRIPTION_USER,
        self::MODEL_TYPE_EVENT_OCCURRENCE_USER,
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function paymentTypeName(): ?string
    {
        return self::PAYMENT_TYPES[$this->payment_type_id] ?? null;
    }

    protected function casts(): array
    {
        return [
            'payment_type_id' => 'integer',
            'model_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}

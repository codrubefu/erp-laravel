<?php

namespace App\Users\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'model_type',
    'model_id',
    'organization_id',
    'subject_user_id',
    'event_type',
    'action',
    'changed_by',
    'old_values',
    'new_values',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const SUBSCRIPTION_ASSIGNED = 'subscription.assigned';
    public const SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    public const SUBSCRIPTION_RENEWED = 'subscription.renewed';
    public const SUBSCRIPTION_SUSPENDED = 'subscription.suspended';
    public const PAYMENT_RECORDED = 'payment.recorded';
    public const APPROVAL_GRANTED = 'approval.granted';
    public const CARD_ISSUED = 'card.issued';
    public const SMS_SENT = 'sms.sent';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}

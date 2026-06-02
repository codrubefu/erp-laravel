<?php

namespace App\Events\Models;

use App\Subscription\Models\Subscription;
use App\Users\Models\Organization;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title','description','location','start_time','end_time','recurrence_type','recurrence_days','monthly_day','start_date','end_date','requires_active_subscription','required_subscription_id','requires_payment','payment_amount','payment_type','max_participants','status','organization_id',
])]
class Event extends Model
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;

    use HasFactory, SoftDeletes;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    public function requiredSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'required_subscription_id');
    }

    protected function casts(): array
    {
        return ['recurrence_days' => 'array','monthly_day' => 'integer','start_date' => 'date:Y-m-d','end_date' => 'date:Y-m-d','requires_active_subscription' => 'boolean','requires_payment' => 'boolean','payment_amount' => 'decimal:2','max_participants' => 'integer'];
    }
}

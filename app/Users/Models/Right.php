<?php

namespace App\Users\Models;

use App\Users\Models\Organization;

use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'label', 'description', 'organization_id'])]
class Right extends Model
{
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory;

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }
}

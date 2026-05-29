<?php

namespace App\Users\Models;

use App\CustomFields\Models\CustomField;
use App\CustomFields\Models\CustomFieldValue;
use App\Users\Models\Concerns\LogsModelChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description'])]
class Organization extends Model
{
    use LogsModelChanges;
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}

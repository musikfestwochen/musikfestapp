<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use App\Models\Organization;
use App\Models\User;
use Database\Factories\Peoplecount\SensorShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sensor_id
 * @property int $owner_organization_id
 * @property int $borrower_organization_id
 * @property int|null $created_by
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'sensor_id',
    'owner_organization_id',
    'borrower_organization_id',
    'created_by',
    'starts_at',
    'ends_at',
])]
#[Table(name: 'peoplecount_sensor_shares')]
class SensorShare extends Model
{
    /** @use HasFactory<SensorShareFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function borrowerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'borrower_organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}

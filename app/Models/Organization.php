<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $logo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'email',
    'phone',
    'website',
    'logo',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The users that belong to the organization.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The sensors that belong to the organization.
     *
     * @return HasMany<Peoplecount\Sensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(Peoplecount\Sensor::class);
    }

    /**
     * The events that belong to the organization.
     *
     * @return HasMany<Peoplecount\Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Peoplecount\Event::class);
    }

    /**
     * The areas that belong to the organization's events.
     *
     * @return HasManyThrough<Peoplecount\Area, Peoplecount\Event, $this>
     */
    public function areas(): HasManyThrough
    {
        return $this->hasManyThrough(Peoplecount\Area::class, Peoplecount\Event::class);
    }

    /**
     * The assignments that belong to the organization's events.
     *
     * @return HasManyThrough<Peoplecount\Assignment, Peoplecount\Event, $this>
     */
    public function assignments(): HasManyThrough
    {
        return $this->hasManyThrough(Peoplecount\Assignment::class, Peoplecount\Event::class);
    }

    /**
     * Sensor shares this organization has created for other organizations.
     *
     * @return HasMany<Peoplecount\SensorShare, $this>
     */
    public function lentSensorShares(): HasMany
    {
        return $this->hasMany(Peoplecount\SensorShare::class, 'owner_organization_id');
    }

    /**
     * Sensor shares this organization can use from other organizations.
     *
     * @return HasMany<Peoplecount\SensorShare, $this>
     */
    public function borrowedSensorShares(): HasMany
    {
        return $this->hasMany(Peoplecount\SensorShare::class, 'borrower_organization_id');
    }
}

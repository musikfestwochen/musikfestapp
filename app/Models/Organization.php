<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
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
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'website',
        'logo',
    ];

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
}

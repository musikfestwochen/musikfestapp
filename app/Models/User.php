<?php

namespace App\Models;

use App\Models\Peoplecount\AreaSingleReset;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'eastereggs_activated',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @pest-mutate-ignore
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The organizations that belong to the user.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    /**
     * The Area Single Resets created by the user.
     *
     * @return HasMany<AreaSingleReset, $this>
     */
    public function areaSingleResets(): HasMany
    {
        return $this->hasMany(AreaSingleReset::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @pest-mutate-ignore
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'eastereggs_activated' => 'boolean',
        ];
    }

    /**
     * Set the phone attribute with automatic formatting cleanup.
     *
     * @return Attribute<string, array<string, string|null>>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: function (?string $value): array {
            $cleaned = $value !== null ? trim($value) : null;
            if ($cleaned === null || $cleaned === '' || $cleaned === '0') {
                return ['phone' => null];
            }

            $cleaned = preg_replace('/[\s\-()\.]+/', '', $cleaned);

            return ['phone' => $cleaned];
        });
    }

    /**
     * Route notifications for the Vonage channel.
     */
    public function routeNotificationForVonage(Notification $notification): string
    {
        return $this->phone;
    }
}

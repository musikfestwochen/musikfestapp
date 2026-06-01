<?php

declare(strict_types=1);

namespace App\Models\Peoplecount;

use App\Enums\Peoplecount\AlertChannel;
use App\Enums\Peoplecount\AlertType;
use App\Models\User;
use Database\Factories\Peoplecount\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property AlertType $type
 * @property AlertChannel $channel
 * @property int $cooldown_minutes
 * @property int|null $occupancy_alert_threshold
 * @property int|null $created_by
 * @property Carbon|null $last_triggered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'area_id',
    'type',
    'channel',
    'cooldown_minutes',
    'created_by',
    'occupancy_alert_threshold',
])]
#[Table(name: 'peoplecount_alerts')]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * The Area that owns the alert.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * The User who created the alert (nullable, set null on user delete).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Users who receive this alert.
     *
     * @return BelongsToMany<User, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'peoplecount_alert_user', 'alert_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'channel' => AlertChannel::class,
            'last_triggered_at' => 'datetime',
        ];
    }
}

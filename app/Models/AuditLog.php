<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'reason',
        'ip_address',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an auditable action performed by a user against an entity.
     */
    public static function record(?User $actor, string $action, ?Model $entity = null, array $changes = [], ?string $reason = null): self
    {
        return self::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'changes' => $changes ?: null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }
}

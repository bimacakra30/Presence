<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'body',
        'type',
        'data',
        'recipient_type',
        'recipient_id',
        'fcm_token',
        'sent_at',
        'read_at',
        'status',
        'priority',
        'image_url',
        'action_url',
        'scheduled_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_SCHEDULED = 'scheduled';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Type constants
    const TYPE_GENERAL = 'general';
    const TYPE_PRESENCE = 'presence';
    const TYPE_PERMIT = 'permit';
    const TYPE_SALARY = 'salary';
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_SYSTEM = 'system';

    public function recipient()
    {
        return $this->morphTo();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'recipient_id')->where('recipient_type', Employee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'recipient_id')->where('recipient_type', User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function markAsRead()
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now()
        ]);
    }

    public function markAsFailed()
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    public function isRead()
    {
        return !is_null($this->read_at);
    }

    public function isSent()
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isScheduled()
    {
        return $this->status === self::STATUS_SCHEDULED;
    }
}

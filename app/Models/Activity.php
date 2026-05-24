<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;

    public const TYPE_NOTE = 'note';

    public const TYPE_TASK = 'task';

    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_EMAIL_SENT = 'email_sent';

    public const TYPE_EMAIL_OPENED = 'email_opened';

    public const TYPE_EMAIL_CLICKED = 'email_clicked';

    public const TYPE_EMAIL_REPLIED = 'email_replied';

    public const TYPE_EMAIL_BOUNCED = 'email_bounced';

    public const TYPE_EMAIL_UNSUBSCRIBED = 'email_unsubscribed';

    protected $fillable = [
        'type',
        'source',
        'external_id',
        'title',
        'body',
        'metadata',
        'status',
        'due_at',
        'completed_at',
        'occurred_at',
        'emelia_campaign_id',
        'subject_type',
        'subject_id',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at'       => 'datetime',
            'completed_at' => 'datetime',
            'occurred_at'  => 'datetime',
            'metadata'     => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function emeliaCampaign(): BelongsTo
    {
        return $this->belongsTo(EmeliaCampaign::class, 'emelia_campaign_id');
    }
}

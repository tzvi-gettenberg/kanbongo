<?php

namespace App\Models;
use App\Enums\LogAction;
use App\Traits\Filterable;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\TracksActivity;

class TimeEntry extends Model
{
    use Filterable, SoftDeletes, TracksActivity;

    protected $guarded = ['id'];

    // Definim proprietatea aici
    public static bool $disableActivityTracking = true;

    // Definim recordableEvents specific pentru TimeEntry
    public static array $recordableEvents = [
        'created',
        'updated',
        'deleted'
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'added_manually' => 'boolean',
        'billable' => 'boolean',
        'billable_rate' => 'float',
        'stopped_by_system' => 'boolean'
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($entry) {
            Log::create([
                'loggable_type' => self::class,
                'loggable_id' => $entry->id,
                'user_id' => Auth::user() ? Auth::user()->id : null,
                'action' => LogAction::CREATE,
                'old_data' => null,
                'new_data' => $entry->toArray(),
                'task_id' => $entry->task_id,
                'container_id' => $entry->container_id,
            ]);
        });

        static::updated(function ($entry) {
            Log::create([
                'loggable_type' => self::class,
                'loggable_id' => $entry->id,
                'user_id' => Auth::user() ? Auth::user()->id : null,
                'action' => LogAction::UPDATE,
                'old_data' => $entry->getOriginal(),
                'new_data' => $entry->getChanges(),
                'task_id' => $entry->task_id,
                'container_id' => $entry->container_id,
            ]);
        });

        static::deleted(function ($entry) {
            Log::create([
                'loggable_type' => self::class,
                'loggable_id' => $entry->id,
                'user_id' => Auth::user() ? Auth::user()->id : null,
                'action' => LogAction::DELETE,
                'task_id' => $entry->task_id,
                'container_id' => $entry->container_id,
                'old_data' => $entry->toArray(),
                'new_data' => null,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(Log::class, 'loggable');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getTrackedTimeAttribute(): int
    {
        if (!$this->end) {
            return 0;
        }

        return $this->end->diffInSeconds($this->start);
    }

    public function getTrackedTimeDisplayAttribute(): string
    {
        $seconds = $this->tracked_time;
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours >= 1000) {
            return sprintf('%.1fk h', $hours / 1000);
        }
        
        return $hours > 0 
            ? sprintf('%dh %dm', $hours, $minutes)
            : sprintf('%dm', $minutes);
    }
}

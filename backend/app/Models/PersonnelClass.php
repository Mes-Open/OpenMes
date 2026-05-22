<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * ISA-95 Personnel Class — a reusable competency template that groups required
 * skills with minimum certification levels. Used to assert whether a Worker is
 * qualified to fulfil a role on a workstation or process segment.
 */
class PersonnelClass extends Model
{
    use HasFactory, HasTenant;

    /** Strict ranking of cert_level (used to evaluate "level meets requirement"). */
    public const LEVEL_RANK = [
        'trainee'  => 1,
        'operator' => 2,
        'expert'   => 3,
        'trainer'  => 4,
    ];

    public const LEVELS = ['trainee', 'operator', 'expert', 'trainer'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'required_skill_ids',
        'default_required_cert_level',
        'is_active',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                   => 'boolean',
            'required_skill_ids'          => 'array',
            'default_required_cert_level' => 'array',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Eager-load the Skill rows referenced by required_skill_ids.
     */
    public function requiredSkills(): Collection
    {
        $ids = $this->required_skill_ids ?? [];
        if (empty($ids)) {
            return Skill::query()->whereRaw('1 = 0')->get();
        }

        return Skill::query()->whereIn('id', $ids)->get();
    }

    /**
     * Check if a Worker holds every required skill at (at least) the required
     * cert_level, with a still-valid certification.
     *
     * Implementation note: uses a direct DB query against worker_skills rather
     * than the Eloquent relation to keep this fast and side-effect-free even
     * when the caller did not eager-load skills.
     */
    public function workerMeetsRequirements(Worker $worker): bool
    {
        $reqIds    = $this->required_skill_ids ?? [];
        $reqLevels = $this->default_required_cert_level ?? [];

        if (empty($reqIds)) {
            return true;
        }

        $today = now()->toDateString();

        foreach ($reqIds as $skillId) {
            $pivot = DB::table('worker_skills')
                ->where('worker_id', $worker->id)
                ->where('skill_id', $skillId)
                ->where(function ($q) use ($today) {
                    $q->whereNull('certified_until')
                      ->orWhere('certified_until', '>=', $today);
                })
                ->first();

            if (! $pivot) {
                return false;
            }

            $required = $reqLevels[$skillId] ?? 'operator';
            if (! $this->levelMeets($pivot->cert_level, $required)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare cert_level strings using the strict LEVEL_RANK ordering.
     */
    public function levelMeets(string $actual, string $required): bool
    {
        $actualRank   = self::LEVEL_RANK[$actual] ?? 0;
        $requiredRank = self::LEVEL_RANK[$required] ?? 0;

        return $actualRank >= $requiredRank;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionOfficerTransition extends Model
{
    protected $fillable = [
        'election_id',
        'organization_id',
        'status',
        'scheduled_at',
        'approved_at',
        'approved_by',
        'winners_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'winners_snapshot' => 'array',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if a student_id is a completed election winner for a given org.
     * Used in Renew flow to allow re-registration of incumbent winners (e.g., president)
     * who are still is_active/pending elsewhere but were approved by adviser via Settings.
     */
    public static function hasCompletedWinnerWithStudentId(string $studentId, int $organizationId): bool
    {
        $normalizedInput = strtolower(trim((string) $studentId));
        $normalizedNoDash = str_replace(['-', ' '], '', $normalizedInput);
        if ($normalizedInput === '' || $normalizedNoDash === '') {
            return false;
        }

        $transitions = static::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->get(['winners_snapshot']);

        foreach ($transitions as $transition) {
            $snapshot = $transition->winners_snapshot ?? [];
            if (! is_array($snapshot) || empty($snapshot)) {
                continue;
            }
            foreach ($snapshot as $data) {
                if (! is_array($data)) {
                    continue;
                }
                $candidateStudentId = $data['student_id'] ?? null;
                if ($candidateStudentId !== null && $candidateStudentId !== '') {
                    $candNorm = strtolower(trim((string) $candidateStudentId));
                    $candNoDash = str_replace(['-', ' '], '', $candNorm);
                    if ($candNorm === $normalizedInput || $candNoDash === $normalizedNoDash) {
                        return true;
                    }
                }
                // Fallback via user_id if student_id not stored or mismatched format
                $userId = $data['user_id'] ?? null;
                if ($userId) {
                    try {
                        $user = User::whereKey((int) $userId)->first(['student_id']);
                        if ($user && $user->student_id) {
                            $userNorm = strtolower(trim((string) $user->student_id));
                            $userNoDash = str_replace(['-', ' '], '', $userNorm);
                            if ($userNorm === $normalizedInput || $userNoDash === $normalizedNoDash) {
                                return true;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore lookup failure
                    }
                }
            }
        }

        return false;
    }

    /**
     * Batch version for renew duplicate map (email -> linkedId). Returns set of winner student_ids.
     */
    public static function completedWinnerStudentIdsForOrg(int $organizationId): array
    {
        $ids = [];
        $transitions = static::where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->get(['winners_snapshot']);
        foreach ($transitions as $transition) {
            foreach (($transition->winners_snapshot ?? []) as $data) {
                $sid = $data['student_id'] ?? null;
                if ($sid) {
                    $ids[strtolower(trim((string) $sid))] = true;
                    $ids[strtolower(str_replace(['-', ' '], '', trim((string) $sid)))] = true;
                }
                if (! empty($data['user_id'])) {
                    try {
                        $u = User::whereKey((int) $data['user_id'])->first(['student_id']);
                        if ($u && $u->student_id) {
                            $ids[strtolower(trim((string) $u->student_id))] = true;
                            $ids[strtolower(str_replace(['-', ' '], '', trim((string) $u->student_id)))] = true;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }
        return $ids;
    }
}

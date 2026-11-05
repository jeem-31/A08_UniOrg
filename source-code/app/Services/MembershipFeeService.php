<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\StudentPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipFeeService
{
    public const DEFAULT_AMOUNT = 250.00;
    public const TITLE = 'Membership Fee';

    /** @return CarbonImmutable */
    public function cycleStart(?CarbonInterface $date = null): CarbonImmutable
    {
        $today = $date
            ? CarbonImmutable::instance($date)->setTimezone(config('app.timezone'))
            : now(config('app.timezone'))->toImmutable();

        $year = $today->month >= 8 ? $today->year : $today->subYear()->year;

        return CarbonImmutable::create($year, 8, 1, 0, 0, 0, config('app.timezone'));
    }

    public function currentFeeFor(Organization $organization, ?CarbonInterface $date = null): ?Payment
    {
        return Payment::allOrganizations()
            ->where('organization_id', $organization->getKey())
            ->requiredMembershipFee()
            ->whereDate('membership_cycle_start', $this->cycleStart($date)->toDateString())
            ->first();
    }

    public function ensureForOrganization(Organization $organization, ?CarbonInterface $date = null): ?Payment
    {
        if (! $organization->isMotherOrg() || ! $organization->canBeUsed()) {
            return null;
        }

        $cycleStart = $this->cycleStart($date);
        $existing = $this->currentFeeFor($organization, $cycleStart);

        if ($existing instanceof Payment) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($organization, $cycleStart): Payment {
                $existing = Payment::allOrganizations()
                    ->where('organization_id', $organization->getKey())
                    ->requiredMembershipFee()
                    ->whereDate('membership_cycle_start', $cycleStart->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Payment) {
                    return $existing;
                }

                $fee = new Payment();
                $fee->organization_id = $organization->getKey();
                $fee->user_id = null;
                $fee->title = self::TITLE;
                $fee->category = 'membership_fee';
                $fee->amount = self::DEFAULT_AMOUNT;
                $fee->due_date = $cycleStart->toDateString();
                $fee->membership_cycle_start = $cycleStart->toDateString();
                $fee->is_required_membership_fee = DB::raw('TRUE');
                $fee->save();
                $fee->refresh();

                return $fee;
            });
        } catch (QueryException $exception) {
            // A concurrent scheduler/server may have inserted the protected row first.
            $fee = $this->currentFeeFor($organization, $cycleStart);

            if ($fee instanceof Payment) {
                return $fee;
            }

            throw $exception;
        }
    }

    /** @return Collection<int, Payment> */
    public function ensureForActiveMotherOrganizations(?CarbonInterface $date = null): Collection
    {
        return Organization::query()
            ->where('type', 'Mother Org')
            ->get()
            ->filter(fn (Organization $organization): bool => $organization->canBeUsed())
            ->map(fn (Organization $organization): ?Payment => $this->ensureForOrganization($organization, $date))
            ->filter()
            ->values();
    }

    /** @return array{status: string, eligible: bool, fee: ?Payment, payment: ?StudentPayment, payments_url: string} */
    public function eligibilityFor(User $user, Organization $motherOrganization, ?CarbonInterface $date = null): array
    {
        $fee = $this->currentFeeFor($motherOrganization, $date);

        if (! $fee instanceof Payment) {
            return $this->eligibility('unavailable', null, null);
        }

        if (trim((string) $fee->gcash_number) === '') {
            return $this->eligibility('unconfigured', $fee, null);
        }

        $payment = StudentPayment::query()
            ->where('user_id', $user->getKey())
            ->where('payment_id', $fee->getKey())
            ->latest('id')
            ->first();

        if ($payment?->status === 'completed' && $payment->verification_status === 'approved') {
            return $this->eligibility('paid', $fee, $payment);
        }

        if ($payment?->verification_status === 'pending') {
            return $this->eligibility('pending', $fee, $payment);
        }

        if ($payment?->verification_status === 'rejected') {
            return $this->eligibility('rejected', $fee, $payment);
        }

        return $this->eligibility('unpaid', $fee, $payment);
    }

    public function isProtectedMembershipFee(?Payment $payment): bool
    {
        return $payment instanceof Payment && $payment->is_required_membership_fee === true;
    }

    private function eligibility(string $status, ?Payment $fee, ?StudentPayment $payment): array
    {
        return [
            'status' => $status,
            'eligible' => $status === 'paid',
            'fee' => $fee,
            'payment' => $payment,
            'payments_url' => route('payments.index'),
        ];
    }
}

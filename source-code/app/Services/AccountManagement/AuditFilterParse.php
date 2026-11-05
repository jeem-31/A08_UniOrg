<?php

namespace App\Services\AccountManagement;

use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * The result of parsing submitted audit filter state.
 *
 * Parsing keeps every valid filter, records safe rejection reasons for the
 * invalid ones, and never repairs, reorders, or widens a submitted value.
 */
final readonly class AuditFilterParse
{
    /** @var list<string> Date-only inputs are bounded, never reordered. */
    private const DATE_ONLY_FORMAT = 'Y-m-d';

    /** @var list<string> */
    private const DATETIME_FORMATS = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];

    /**
     * @param  list<AuditFilterRejection>  $rejections
     * @param  array<string, string>  $submitted  Raw retained input so a rejected filter can be corrected.
     */
    private function __construct(
        public AccountManagementAuditFilter $filter,
        public array $rejections,
        public array $submitted,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(
        array $input,
        int $perPage = AccountManagementAuditFilter::DEFAULT_PER_PAGE,
    ): self {
        $rejections = [];
        $submitted = [];

        $action = self::action($input, $rejections, $submitted);
        $actorId = self::identifier($input, AccountManagementAuditFilter::PARAM_ACTOR, AuditFilterRejection::InvalidActor, $rejections, $submitted);
        $accountId = self::identifier($input, AccountManagementAuditFilter::PARAM_ACCOUNT, AuditFilterRejection::InvalidAffectedAccount, $rejections, $submitted);
        $organizationId = self::identifier($input, AccountManagementAuditFilter::PARAM_ORGANIZATION, AuditFilterRejection::InvalidOrganization, $rejections, $submitted);

        $start = self::moment($input, AccountManagementAuditFilter::PARAM_FROM, AuditFilterRejection::InvalidStartTime, $rejections, $submitted);
        $end = self::moment($input, AccountManagementAuditFilter::PARAM_TO, AuditFilterRejection::InvalidEndTime, $rejections, $submitted);

        $timeRange = null;

        if (AuditTimeRange::isReversed($start, $end)) {
            $rejections[] = AuditFilterRejection::ReversedTimeRange;
        } elseif ($start !== null || $end !== null) {
            $timeRange = AuditTimeRange::of($start, $end);
        }

        $filter = new AccountManagementAuditFilter(
            $action,
            $actorId === null ? null : new ActorAccountId($actorId),
            $accountId === null ? null : new ManagedAccountId($accountId),
            $organizationId === null ? null : new OrganizationId($organizationId),
            $timeRange,
            self::page($input),
            $perPage,
        );

        return new self($filter, array_values($rejections), $submitted);
    }

    public function acceptable(): bool
    {
        return $this->rejections === [];
    }

    /** @return array<string, string> Field-keyed non-sensitive messages for the audit form. */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->rejections as $rejection) {
            $messages[$rejection->field()] = $rejection->message();
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<AuditFilterRejection>  $rejections
     * @param  array<string, string>  $submitted
     */
    private static function action(array $input, array &$rejections, array &$submitted): ?AccountManagementAction
    {
        $raw = self::raw($input, AccountManagementAuditFilter::PARAM_ACTION);

        if ($raw === null) {
            return null;
        }

        $submitted[AccountManagementAuditFilter::PARAM_ACTION] = $raw;
        $action = AccountManagementAction::tryFrom($raw);

        if ($action === null) {
            $rejections[] = AuditFilterRejection::InvalidAction;
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<AuditFilterRejection>  $rejections
     * @param  array<string, string>  $submitted
     */
    private static function identifier(
        array $input,
        string $parameter,
        AuditFilterRejection $rejection,
        array &$rejections,
        array &$submitted,
    ): ?int {
        $raw = self::raw($input, $parameter);

        if ($raw === null) {
            return null;
        }

        $submitted[$parameter] = $raw;

        if (preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            $rejections[] = $rejection;

            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<AuditFilterRejection>  $rejections
     * @param  array<string, string>  $submitted
     */
    private static function moment(
        array $input,
        string $parameter,
        AuditFilterRejection $rejection,
        array &$rejections,
        array &$submitted,
    ): ?CarbonImmutable {
        $raw = self::raw($input, $parameter);

        if ($raw === null) {
            return null;
        }

        $submitted[$parameter] = $raw;
        $moment = self::parseMoment($raw, $parameter === AccountManagementAuditFilter::PARAM_TO);

        if ($moment === null) {
            $rejections[] = $rejection;
        }

        return $moment;
    }

    private static function parseMoment(string $value, bool $isRangeEnd): ?CarbonImmutable
    {
        foreach (self::DATETIME_FORMATS as $format) {
            $parsed = self::exact($value, $format);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        $dateOnly = self::exact($value, self::DATE_ONLY_FORMAT);

        if ($dateOnly === null) {
            return null;
        }

        return $isRangeEnd ? $dateOnly->endOfDay() : $dateOnly->startOfDay();
    }

    /** Strict parsing only: an unrecognized or out-of-range value is never coerced. */
    private static function exact(string $value, string $format): ?CarbonImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format($format) !== $value) {
            return null;
        }

        return CarbonImmutable::instance($parsed);
    }

    /** @param array<string, mixed> $input */
    private static function page(array $input): int
    {
        $raw = self::raw($input, AccountManagementAuditFilter::PARAM_PAGE);

        if ($raw === null || preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            return 1;
        }

        return (int) $raw;
    }

    /** @param array<string, mixed> $input */
    private static function raw(array $input, string $parameter): ?string
    {
        $value = $input[$parameter] ?? null;

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

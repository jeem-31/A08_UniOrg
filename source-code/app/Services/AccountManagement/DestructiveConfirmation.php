<?php

namespace App\Services\AccountManagement;

use InvalidArgumentException;

/**
 * Exact immutable facts a confirmation token authorizes. It is intentionally
 * separate from form input so requests cannot broaden a destructive action.
 */
final readonly class DestructiveConfirmation
{
    private function __construct(
        public DestructiveConfirmationAction $action,
        public int $actorId,
        public int $targetId,
        public int $targetVersion,
        public ?int $formerDeanId = null,
        public ?int $formerDeanVersion = null,
        public ?NonDeanOutcome $formerDeanOutcome = null,
    ) {
        if ($actorId < 1 || $targetId < 1 || $targetVersion < 1) {
            throw new InvalidArgumentException('Invalid destructive confirmation binding.');
        }

        $isReplacement = $action === DestructiveConfirmationAction::DeanReplacement;
        if ($isReplacement !== ($formerDeanId !== null && $formerDeanVersion !== null && $formerDeanOutcome !== null)
            || ($formerDeanId !== null && ($formerDeanId < 1 || $formerDeanId === $targetId))
            || ($formerDeanVersion !== null && $formerDeanVersion < 1)) {
            throw new InvalidArgumentException('Invalid destructive confirmation binding.');
        }
    }

    public static function forAccountDeletion(int $actorId, int $targetId, int $targetVersion): self
    {
        return new self(DestructiveConfirmationAction::AccountDeletion, $actorId, $targetId, $targetVersion);
    }

    public static function forDeanReplacement(
        int $actorId,
        int $incomingDeanId,
        int $incomingDeanVersion,
        int $formerDeanId,
        int $formerDeanVersion,
        NonDeanOutcome $formerDeanOutcome,
    ): self {
        return new self(
            DestructiveConfirmationAction::DeanReplacement,
            $actorId,
            $incomingDeanId,
            $incomingDeanVersion,
            $formerDeanId,
            $formerDeanVersion,
            $formerDeanOutcome,
        );
    }

    /** @return array<string, int|string|array<string, string|null>|null> */
    public function toPayload(): array
    {
        return [
            'action' => $this->action->value,
            'actor_id' => $this->actorId,
            'target_id' => $this->targetId,
            'target_version' => $this->targetVersion,
            'former_dean_id' => $this->formerDeanId,
            'former_dean_version' => $this->formerDeanVersion,
            'former_dean_outcome' => $this->formerDeanOutcome?->toPayload(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if (array_keys($payload) !== [
            'action', 'actor_id', 'target_id', 'target_version',
            'former_dean_id', 'former_dean_version', 'former_dean_outcome',
        ] || ! is_string($payload['action']) || ! is_int($payload['actor_id'])
            || ! is_int($payload['target_id']) || ! is_int($payload['target_version'])
            || ($payload['former_dean_id'] !== null && ! is_int($payload['former_dean_id']))
            || ($payload['former_dean_version'] !== null && ! is_int($payload['former_dean_version']))
            || ($payload['former_dean_outcome'] !== null && ! is_array($payload['former_dean_outcome']))) {
            throw new InvalidArgumentException('Invalid destructive confirmation payload.');
        }

        try {
            $action = DestructiveConfirmationAction::from($payload['action']);
        } catch (\ValueError) {
            throw new InvalidArgumentException('Invalid destructive confirmation payload.');
        }

        return new self(
            $action,
            $payload['actor_id'],
            $payload['target_id'],
            $payload['target_version'],
            $payload['former_dean_id'],
            $payload['former_dean_version'],
            $payload['former_dean_outcome'] === null ? null : NonDeanOutcome::fromPayload($payload['former_dean_outcome']),
        );
    }
}

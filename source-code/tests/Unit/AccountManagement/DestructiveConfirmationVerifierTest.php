<?php

namespace Tests\Unit\AccountManagement;

use App\Services\AccountManagement\CanceledDestructiveConfirmation;
use App\Services\AccountManagement\ConsumedDestructiveConfirmation;
use App\Services\AccountManagement\DestructiveConfirmation;
use App\Services\AccountManagement\DestructiveConfirmationAccepted;
use App\Services\AccountManagement\DestructiveConfirmationGuard;
use App\Services\AccountManagement\DestructiveConfirmationVerifier;
use App\Services\AccountManagement\ExpiredDestructiveConfirmation;
use App\Services\AccountManagement\MismatchedDestructiveConfirmation;
use App\Services\AccountManagement\MissingDestructiveConfirmation;
use App\Services\AccountManagement\NonDeanOutcome;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Tests\TestCase;

/** **Validates: Requirements 4.6, 5.3, 10.4, 10.5, 10.6** */
final class DestructiveConfirmationVerifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_valid_delete_confirmation_reaches_mutation_once_only(): void
    {
        $confirmation = DestructiveConfirmation::forAccountDeletion(10, 20, 3);
        $verifier = $this->verifier();
        $guard = new DestructiveConfirmationGuard($verifier);
        $token = $verifier->issue($confirmation);
        $mutations = 0;

        $accepted = $guard->run($token, $confirmation, static function () use (&$mutations): void {
            $mutations++;
        });
        $consumed = $guard->run($token, $confirmation, static function () use (&$mutations): void {
            $mutations++;
        });

        self::assertInstanceOf(DestructiveConfirmationAccepted::class, $accepted);
        self::assertInstanceOf(ConsumedDestructiveConfirmation::class, $consumed);
        self::assertSame(1, $mutations);
    }

    public function test_dean_replacement_token_binds_every_dean_and_outcome_fact(): void
    {
        $expected = $this->replacementConfirmation();
        $changedBindings = [
            DestructiveConfirmation::forDeanReplacement(11, 22, 8, 33, 4, NonDeanOutcome::deactivate()),
            DestructiveConfirmation::forDeanReplacement(10, 22, 9, 33, 4, NonDeanOutcome::deactivate()),
            DestructiveConfirmation::forDeanReplacement(10, 22, 8, 33, 5, NonDeanOutcome::deactivate()),
            DestructiveConfirmation::forDeanReplacement(10, 22, 8, 33, 4, NonDeanOutcome::reassign('admin')),
        ];

        foreach ($changedBindings as $changed) {
            $verifier = $this->verifier();
            $mutations = 0;
            $result = (new DestructiveConfirmationGuard($verifier))->run(
                $verifier->issue($expected),
                $changed,
                static function () use (&$mutations): void {
                    $mutations++;
                },
            );

            self::assertInstanceOf(MismatchedDestructiveConfirmation::class, $result);
            self::assertSame(0, $mutations);
        }
    }

    public function test_missing_canceled_expired_and_tampered_confirmations_fail_closed(): void
    {
        Carbon::setTestNow('2026-07-25 12:00:00');
        $confirmation = $this->replacementConfirmation();
        $verifier = $this->verifier(ttlSeconds: 60);
        $guard = new DestructiveConfirmationGuard($verifier);
        $mutations = 0;
        $mutation = static function () use (&$mutations): void {
            $mutations++;
        };

        $missing = $guard->run(null, $confirmation, $mutation);
        $cancelToken = $verifier->issue($confirmation);
        $canceled = $guard->run($cancelToken, $confirmation, $mutation, canceled: true);
        $consumedAfterCancel = $guard->run($cancelToken, $confirmation, $mutation);
        $expiredToken = $verifier->issue($confirmation);
        Carbon::setTestNow('2026-07-25 12:01:00');
        $expired = $guard->run($expiredToken, $confirmation, $mutation);
        $tamperedToken = $verifier->issue($confirmation);
        $lastCharacter = substr($tamperedToken, -1);
        $tampered = $guard->run(
            substr($tamperedToken, 0, -1).($lastCharacter === 'A' ? 'B' : 'A'),
            $confirmation,
            $mutation,
        );

        self::assertInstanceOf(MissingDestructiveConfirmation::class, $missing);
        self::assertInstanceOf(CanceledDestructiveConfirmation::class, $canceled);
        self::assertInstanceOf(ConsumedDestructiveConfirmation::class, $consumedAfterCancel);
        self::assertInstanceOf(ExpiredDestructiveConfirmation::class, $expired);
        self::assertInstanceOf(MismatchedDestructiveConfirmation::class, $tampered);
        self::assertSame(0, $mutations);
    }

    private function verifier(int $ttlSeconds = 300): DestructiveConfirmationVerifier
    {
        return new DestructiveConfirmationVerifier(
            $this->app->make(Encrypter::class),
            $this->app->make(Repository::class),
            $ttlSeconds,
        );
    }

    private function replacementConfirmation(): DestructiveConfirmation
    {
        return DestructiveConfirmation::forDeanReplacement(
            actorId: 10,
            incomingDeanId: 22,
            incomingDeanVersion: 8,
            formerDeanId: 33,
            formerDeanVersion: 4,
            formerDeanOutcome: NonDeanOutcome::deactivate(),
        );
    }
}

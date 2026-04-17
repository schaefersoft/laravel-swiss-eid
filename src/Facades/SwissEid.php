<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Facades;

use Illuminate\Support\Facades\Facade;
use SwissEid\LaravelSwissEid\DTOs\PendingVerification;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\SwissEidFake;
use SwissEid\LaravelSwissEid\SwissEidManager;

/**
 * @method static SwissEidManager verify()
 * @method static SwissEidManager ageOver18()
 * @method static SwissEidManager ageOver16()
 * @method static SwissEidManager fields(array $fields)
 * @method static SwissEidManager field(string $path)
 * @method static SwissEidManager credentialType(string $type)
 * @method static SwissEidManager acceptedIssuers(array $dids)
 * @method static SwissEidManager forUser(int|string $userId)
 * @method static SwissEidManager metadata(array $data)
 * @method static PendingVerification create()
 * @method static VerificationResult getVerification(string $id)
 * @method static SwissEidFake fake(array $responses = [])
 * @method static void assertVerificationStarted()
 * @method static void assertVerificationCompleted(?callable $callback = null)
 * @method static void assertNothingStarted()
 *
 * @see SwissEidManager
 */
class SwissEid extends Facade
{
    /**
     * Replace the underlying manager with a fake for testing.
     *
     * @param  array<string, mixed>  $responses
     */
    public static function fake(array $responses = []): SwissEidFake
    {
        $fake = SwissEidFake::make($responses);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'swiss-eid';
    }
}

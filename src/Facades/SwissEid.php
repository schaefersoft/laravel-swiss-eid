<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Facades;

use Illuminate\Support\Facades\Facade;
use SwissEid\LaravelSwissEid\DTOs\VerificationResult;
use SwissEid\LaravelSwissEid\Enums\CredentialField;
use SwissEid\LaravelSwissEid\SwissEidFake;
use SwissEid\LaravelSwissEid\SwissEidManager;
use SwissEid\LaravelSwissEid\VerificationRequest;

/**
 * @method static VerificationRequest verify()
 * @method static VerificationRequest ageOver18()
 * @method static VerificationRequest ageOver16()
 * @method static VerificationRequest fields(array<int, string|CredentialField> $fields)
 * @method static VerificationRequest field(string $path)
 * @method static VerificationRequest credentialType(string|list<string> $type)
 * @method static VerificationRequest purpose(string $scope, string|array<string, string> $name, string|array<string, string> $description)
 * @method static VerificationRequest responseMode(string $mode)
 * @method static VerificationRequest acceptedIssuers(list<string> $dids)
 * @method static VerificationRequest trustAnchors(list<array{did: string, trust_registry_uri: string}> $anchors)
 * @method static VerificationRequest forUser(int|string $userId)
 * @method static VerificationRequest metadata(array<string, mixed> $data)
 * @method static VerificationResult getVerification(string $id)
 * @method static VerificationResult refresh(string $id)
 * @method static SwissEidFake fake(array<string, mixed> $responses = [])
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

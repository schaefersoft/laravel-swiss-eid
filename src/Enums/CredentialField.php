<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Enums;

enum CredentialField: string
{
    case AgeOver18 = 'age_over_18';
    case AgeOver16 = 'age_over_16';
    case GivenName = 'given_name';
    case FamilyName = 'family_name';
    case DateOfBirth = 'birth_date';
    case Nationality = 'nationality';
    case PlaceOfBirth = 'place_of_birth';
    case Gender = 'gender';

    /**
     * Return the DCQL claim path for this field (e.g. ['age_over_18']).
     *
     * @return list<string>
     */
    public function path(): array
    {
        return [$this->value];
    }

    /**
     * Return the legacy DIF Presentation Exchange JSON path (e.g. '$.age_over_18').
     *
     * @deprecated The swiyu Verifier 3.0.0 uses DCQL; use {@see path()} instead.
     */
    public function jsonPath(): string
    {
        return '$.'.$this->value;
    }
}

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
     * Return the JSON path used in a presentation definition (e.g. '$.age_over_18').
     */
    public function jsonPath(): string
    {
        return '$.'.$this->value;
    }
}

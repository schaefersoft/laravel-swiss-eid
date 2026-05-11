<?php

declare(strict_types=1);

namespace SwissEid\LaravelSwissEid\Enums;

enum VerificationState: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Expired = 'expired';

    /**
     * Whether no further state changes are expected.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Success, self::Failed, self::Expired => true,
            self::Pending => false,
        };
    }

    public function label(): string
    {
        return (string) trans('swiss-eid::states.'.$this->value);
    }
}

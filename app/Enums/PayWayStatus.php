<?php

declare(strict_types=1);

namespace App\Enums;

enum PayWayStatus: string
{
    case APPROVED = '0';
    case CANCELED = '200';
    case DECLINED = '201';

    public function isPaid(): bool
    {
        return $this === self::APPROVED;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::CANCELED, self::DECLINED], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\GenerateDigest;

enum GenerateDigestPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
}

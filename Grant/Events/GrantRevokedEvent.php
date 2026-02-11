<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Events;

use DateTimeImmutable;

/**
 * Grant Revoked Event
 * 
 * Emitted when a grant is manually revoked.
 * Requirements: 8.4
 */
class GrantRevokedEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly DateTimeImmutable $revokedAt
    ) {}
}

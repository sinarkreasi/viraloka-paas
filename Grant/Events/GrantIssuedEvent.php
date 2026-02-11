<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Events;

use DateTimeImmutable;

/**
 * Grant Issued Event
 * 
 * Emitted when a new grant is issued.
 * Requirements: 8.1
 */
class GrantIssuedEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly string $identityId,
        public readonly string $workspaceId,
        public readonly string $role,
        public readonly array $constraints,
        public readonly DateTimeImmutable $issuedAt
    ) {}
}

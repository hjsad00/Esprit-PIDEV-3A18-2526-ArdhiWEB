<?php

namespace App\Security;

use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;

class CustomResetPasswordRequest implements ResetPasswordRequestInterface
{
    public function __construct(
        private object $user,
        private \DateTimeInterface $expiresAt,
        private string $selector,
        private string $hashedToken,
        private ?\DateTimeInterface $requestedAt = null
    ) {
        $this->requestedAt = $requestedAt ?? new \DateTimeImmutable();
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function getRequestedAt(): \DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->getTimestamp() <= time();
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }
}

<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\UserBlock;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setEmail('test@example.com');
        $this->user->setNom('Dupont');
        $this->user->setPrenom('Jean');
        $this->user->setPassword('hashed_password');
    }

    // ── Getters / Setters ──

    public function testGetSetEmail(): void
    {
        $this->assertSame('test@example.com', $this->user->getEmail());
        $this->user->setEmail('new@test.com');
        $this->assertSame('new@test.com', $this->user->getEmail());
    }

    public function testGetSetNom(): void
    {
        $this->assertSame('Dupont', $this->user->getNom());
    }

    public function testGetSetPrenom(): void
    {
        $this->assertSame('Jean', $this->user->getPrenom());
    }

    public function testGetUserIdentifier(): void
    {
        $this->assertSame('test@example.com', $this->user->getUserIdentifier());
    }

    public function testDefaultValues(): void
    {
        $user = new User();
        $this->assertSame(0, $user->getPoints());
        $this->assertSame(1, $user->getLevel());
        $this->assertFalse($user->isTwoFactorEnabled());
        $this->assertFalse($user->isModerator());
        $this->assertFalse($user->isBanned());
        $this->assertSame(0.0, $user->getPointsFidelite());
    }

    // ── getRoles() logic ──

    public function testGetRolesDefaultAgriculteur(): void
    {
        $this->user->setRole('AGRICULTEUR');
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_AGRICULTEUR', $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertNotContains('ROLE_MODERATOR', $roles);
    }

    public function testGetRolesAdmin(): void
    {
        $this->user->setRole('ADMIN');
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_MODERATOR', $roles); // Admin = always moderator
    }

    public function testGetRolesModeratorPromoted(): void
    {
        $this->user->setRole('AGRICULTEUR');
        $this->user->setIsModerator(true);
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_MODERATOR', $roles);
        $this->assertContains('ROLE_AGRICULTEUR', $roles);
    }

    // ── Reset Password auto-expiry ──

    public function testSetResetPasswordCodeSetsExpiry(): void
    {
        $this->user->setResetPasswordCode('ABC123');
        $this->assertSame('ABC123', $this->user->getResetPasswordCode());
        $this->assertNotNull($this->user->getResetPasswordExpiresAt());
        $this->assertGreaterThan(new \DateTime(), $this->user->getResetPasswordExpiresAt());
    }

    public function testSetResetPasswordCodeNullClearsExpiry(): void
    {
        $this->user->setResetPasswordCode('ABC123');
        $this->user->setResetPasswordCode(null);
        $this->assertNull($this->user->getResetPasswordCode());
        $this->assertNull($this->user->getResetPasswordExpiresAt());
    }

    // ── 2FA email auth code ──

    public function testSetEmailAuthCodeSetsExpiry(): void
    {
        $this->user->setEmailAuthCode('123456');
        $this->assertSame('123456', $this->user->getEmailAuthCode());
        $this->assertNotNull($this->user->getTwoFactorExpiresAt());
    }

    public function testSetEmailAuthCodeNullClearsExpiry(): void
    {
        $this->user->setEmailAuthCode('123456');
        $this->user->setEmailAuthCode(null);
        $this->assertNull($this->user->getEmailAuthCode());
        $this->assertNull($this->user->getTwoFactorExpiresAt());
    }

    public function testGetEmailAuthCodeReturnsNullWhenExpired(): void
    {
        $this->user->setTwoFactorCode('123456');
        $this->user->setTwoFactorExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->assertNull($this->user->getEmailAuthCode());
    }

    // ── Blocking ──

    public function testBlockAndUnblockUser(): void
    {
        $blocked = new User();
        $blocked->setEmail('blocked@test.com');

        $this->assertFalse($this->user->isBlocking($blocked));

        $this->user->addBlockedUser($blocked);
        $this->assertTrue($this->user->isBlocking($blocked));
        $this->assertCount(1, $this->user->getUserBlocks());

        // Adding the same user again should not duplicate
        $this->user->addBlockedUser($blocked);
        $this->assertCount(1, $this->user->getUserBlocks());

        $this->user->removeBlockedUser($blocked);
        $this->assertFalse($this->user->isBlocking($blocked));
        $this->assertCount(0, $this->user->getUserBlocks());
    }

    // ── Points & Level ──

    public function testSetPoints(): void
    {
        $this->user->setPoints(1500);
        $this->assertSame(1500, $this->user->getPoints());
    }

    public function testSetLevel(): void
    {
        $this->user->setLevel(5);
        $this->assertSame(5, $this->user->getLevel());
    }

    // ── Optional fields ──

    public function testPhoneAndLocation(): void
    {
        $this->user->setPhone('+21612345678');
        $this->assertSame('+21612345678', $this->user->getPhone());

        $this->user->setLocation('Tunis, Tunisie');
        $this->assertSame('Tunis, Tunisie', $this->user->getLocation());
    }

    public function testAvatarBannerBio(): void
    {
        $this->user->setAvatar('avatar.jpg');
        $this->assertSame('avatar.jpg', $this->user->getAvatar());

        $this->user->setBanner('banner.jpg');
        $this->assertSame('banner.jpg', $this->user->getBanner());

        $this->user->setBio('Ma bio');
        $this->assertSame('Ma bio', $this->user->getBio());
    }

    // ── 2FA interface ──

    public function testIsEmailAuthEnabled(): void
    {
        $this->user->setTwoFactorEnabled(true);
        $this->assertTrue($this->user->isEmailAuthEnabled());

        $this->assertSame('test@example.com', $this->user->getEmailAuthRecipient());
    }

    // ── Muted / Banned ──

    public function testMutedUntil(): void
    {
        $future = new \DateTime('+1 day');
        $this->user->setMutedUntil($future);
        $this->assertSame($future, $this->user->getMutedUntil());
    }

    public function testBanned(): void
    {
        $this->user->setIsBanned(true);
        $this->assertTrue($this->user->isBanned());
    }
}

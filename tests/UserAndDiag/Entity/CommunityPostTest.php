<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\User;
use PHPUnit\Framework\TestCase;

class CommunityPostTest extends TestCase
{
    private CommunityPost $post;

    protected function setUp(): void
    {
        $this->post = new CommunityPost();
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->post->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->post->getCreatedAt());
    }

    public function testDefaultCountersAreZero(): void
    {
        $this->assertSame(0, $this->post->getLikes());
        $this->assertSame(0, $this->post->getDislikes());
        $this->assertSame(0, $this->post->getViews());
        $this->assertSame(0, $this->post->getFeedImpressions());
        $this->assertSame(0, $this->post->getTotalReadTime());
        $this->assertSame(0, $this->post->getTotalFeedDwellTime());
        $this->assertSame(0, $this->post->getCompletedReads());
        $this->assertSame(0, $this->post->getMediaClicks());
    }

    public function testDefaultIsNotResolved(): void
    {
        $this->assertFalse($this->post->isResolved());
    }

    public function testGetSetTitle(): void
    {
        $this->post->setTitle('Ma plante est malade');
        $this->assertSame('Ma plante est malade', $this->post->getTitle());
    }

    public function testGetSetDescription(): void
    {
        $this->post->setDescription('Les feuilles jaunissent depuis une semaine.');
        $this->assertSame('Les feuilles jaunissent depuis une semaine.', $this->post->getDescription());
    }

    public function testGetSetUser(): void
    {
        $user = new User();
        $user->setEmail('poster@test.com');

        $this->post->setUser($user);
        $this->assertSame($user, $this->post->getUser());
    }

    public function testGetSetImageUrl(): void
    {
        $this->post->setImageUrl('https://imgbb.com/photo.jpg');
        $this->assertSame('https://imgbb.com/photo.jpg', $this->post->getImageUrl());
    }

    public function testLikesAndDislikes(): void
    {
        $this->post->setLikes(15);
        $this->post->setDislikes(3);

        $this->assertSame(15, $this->post->getLikes());
        $this->assertSame(3, $this->post->getDislikes());
    }

    public function testAnalyticsCounters(): void
    {
        $this->post->setViews(100);
        $this->post->setFeedImpressions(500);
        $this->post->setTotalReadTime(3600);
        $this->post->setCompletedReads(80);
        $this->post->setMediaClicks(25);

        $this->assertSame(100, $this->post->getViews());
        $this->assertSame(500, $this->post->getFeedImpressions());
        $this->assertSame(3600, $this->post->getTotalReadTime());
        $this->assertSame(80, $this->post->getCompletedReads());
        $this->assertSame(25, $this->post->getMediaClicks());
    }

    public function testMarkAsResolved(): void
    {
        $this->post->setIsResolved(true);
        $this->assertTrue($this->post->isResolved());
    }
}

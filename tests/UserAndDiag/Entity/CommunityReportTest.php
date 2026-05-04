<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\CommunityReport;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\User;
use PHPUnit\Framework\TestCase;

class CommunityReportTest extends TestCase
{
    private CommunityReport $report;

    protected function setUp(): void
    {
        $this->report = new CommunityReport();
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->report->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->report->getCreatedAt());
    }

    public function testDefaultIsNotResolved(): void
    {
        $this->assertFalse($this->report->isResolved());
    }

    public function testGetSetReporter(): void
    {
        $user = new User();
        $user->setEmail('reporter@test.com');

        $this->report->setReporter($user);
        $this->assertSame($user, $this->report->getReporter());
    }

    public function testGetSetPost(): void
    {
        $post = new CommunityPost();
        $post->setTitle('Post signalé');

        $this->report->setPost($post);
        $this->assertSame($post, $this->report->getPost());
    }

    public function testGetSetReason(): void
    {
        $this->report->setReason('Contenu inapproprié');
        $this->assertSame('Contenu inapproprié', $this->report->getReason());
    }

    public function testResolveReport(): void
    {
        $this->report->setIsResolved(true);
        $this->assertTrue($this->report->isResolved());
    }

    public function testReportCanTargetPostOrComment(): void
    {
        // Report targeting a post
        $post = new CommunityPost();
        $this->report->setPost($post);
        $this->assertSame($post, $this->report->getPost());
        $this->assertNull($this->report->getComment());
    }
}

<?php

namespace App\Tests\UserAndDiag\Entity;

use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\PreventionTask;
use App\Entity\UserAndDiag\FarmHealthReport;
use PHPUnit\Framework\TestCase;

class PreventionPlanTest extends TestCase
{
    private PreventionPlan $plan;

    protected function setUp(): void
    {
        $this->plan = new PreventionPlan();
    }

    public function testConstructorDefaults(): void
    {
        $this->assertNotNull($this->plan->getCreatedAt());
        $this->assertCount(0, $this->plan->getTasks());
        $this->assertSame('ACTIVE', $this->plan->getStatus());
    }

    public function testGetSetTitle(): void
    {
        $this->plan->setTitle('Plan anti-mildiou');
        $this->assertSame('Plan anti-mildiou', $this->plan->getTitle());
    }

    public function testGetSetSteps(): void
    {
        $this->plan->setSteps("1|Inspection\n2|Traitement");
        $this->assertSame("1|Inspection\n2|Traitement", $this->plan->getSteps());
    }

    public function testGetSetTimelineDays(): void
    {
        $this->plan->setTimelineDays(14);
        $this->assertSame(14, $this->plan->getTimelineDays());
    }

    public function testAddAndRemoveTask(): void
    {
        $task = new PreventionTask();
        $this->plan->addTask($task);
        $this->assertCount(1, $this->plan->getTasks());
        $this->assertSame($this->plan, $task->getPreventionPlan());

        $this->plan->addTask($task);
        $this->assertCount(1, $this->plan->getTasks());

        $this->plan->removeTask($task);
        $this->assertCount(0, $this->plan->getTasks());
    }

    public function testSetStatus(): void
    {
        $this->plan->setStatus('COMPLETED');
        $this->assertSame('COMPLETED', $this->plan->getStatus());
    }

    public function testGetSetReport(): void
    {
        $report = new FarmHealthReport();
        $this->plan->setReport($report);
        $this->assertSame($report, $this->plan->getReport());
    }
}

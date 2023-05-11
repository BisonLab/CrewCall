<?php

namespace App\Tests\JobLog;

use App\Entity\Shift;
use App\Entity\Job;
use App\Entity\JobLog;

use PHPUnit\Framework\TestCase;

class TimesTest extends TestCase
{
    private ?Shift $shift;
    private ?Job $job;

    public function setUp(): void
    {
        $this->shift = new Shift();
        $start = new \DateTime('2020-04-01 07:00');
        $end = new \DateTime('2020-04-01 16:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $this->job = new Job();
        $this->job->setShift($this->shift);
    }

    public function testJobHasDates(): void
    {
        $this->assertSame($this->job->getStart()->format('Y-m-d H:i'), '2020-04-01 07:00');
        $this->assertSame($this->job->getEnd()->format('Y-m-d H:i'), '2020-04-01 16:00');
    }

    public function testBadSetIn1(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        $this->expectException(\InvalidArgumentException::class);
        $joblog->setInTime('32:00');
    }

    public function testBadSetIn2(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        $this->expectException(\InvalidArgumentException::class);
        $joblog->setInTime('15:69');
    }

    public function testBadSetIn3(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        $this->expectException(\Error::class);
        $joblog->setInTime('06:00');
        $this->assertSame('15:00', $joblog->getWorkedTime());
    }

    public function testSetIn(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);

        // Simple and clean. Shift 07 -> 16
        $joblog->setInTime('10:00');
        $joblog->setOutTime('11:00');
        $this->assertSame('2020-04-01 10:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-01 11:00', $joblog->getOut()->format('Y-m-d H:i'));

        // A tad earlier. Shift 07 -> 16
        $joblog->setInTime('07:00');
        $joblog->setOutTime('11:00');
        $this->assertSame('2020-04-01 07:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-01 11:00', $joblog->getOut()->format('Y-m-d H:i'));

        // Stupid times. Shift 07 -> 16
        $joblog->setInTime('11:00');
        $this->assertSame($joblog->getIn()->format('Y-m-d H:i'), '2020-04-01 11:00');
        // The question is, should I care?
        $joblog->setInTime('23:00');
        $this->assertSame($joblog->getIn()->format('Y-m-d H:i'), '2020-03-31 23:00');

        // Started the day before?
        $start = new \DateTime('2020-04-01 01:00');
        $end = new \DateTime('2020-04-01 07:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $joblog->setInTime('23:00');
        $joblog->setOutTime('07:00');
        $this->assertSame('2020-03-31 23:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-01 07:00', $joblog->getOut()->format('Y-m-d H:i') );

        // Shift rolling over
        $start = new \DateTime('2020-04-01 22:00');
        $end = new \DateTime('2020-04-02 07:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $joblog->setInTime('23:00');
        $joblog->setOutTime('08:00');
        $this->assertSame('2020-04-01 23:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 08:00', $joblog->getOut()->format('Y-m-d H:i') );

        // Started the day after?
        $joblog->setInTime('01:00');
        $joblog->setOutTime('07:00');
        $this->assertSame('2020-04-02 01:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 07:00', $joblog->getOut()->format('Y-m-d H:i') );

    }

    public function testBadSetOut(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        $this->expectException(\InvalidArgumentException::class);
        $joblog->setOutTime('11:00');
    }

    public function testSetOut(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);

        // Simple and clean. Shift 07 -> 16
        $joblog->setInTime('07:00');
        $joblog->setOutTime('16:00');
        $this->assertSame('2020-04-01 07:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-01 16:00', $joblog->getOut()->format('Y-m-d H:i') );
        // A little adjustment
        $joblog->setOutTime('22:00');
        $this->assertSame('2020-04-01 22:00', $joblog->getOut()->format('Y-m-d H:i') );

        // A bloody long time Shift: 07 -> 16
        $joblog->setInTime('10:00');
        $joblog->setOutTime('01:00');
        $this->assertSame('2020-04-01 10:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 01:00', $joblog->getOut()->format('Y-m-d H:i'));
        $this->assertSame('15:00', $joblog->getWorkedTime());

        // Does it roll over correctly?
        $start = new \DateTime('2020-04-01 22:00');
        $end = new \DateTime('2020-04-02 07:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $this->assertSame('2020-04-01 22:00', $this->job->getStart()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 07:00', $this->job->getEnd()->format('Y-m-d H:i'));
        $joblog->setInTime('01:00');
        $this->assertSame('2020-04-02 01:00', $joblog->getIn()->format('Y-m-d H:i'));

        // And not?
        $start = new \DateTime('2020-04-01 19:00');
        $end = new \DateTime('2020-04-02 03:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $joblog->setInTime('18:00');
        $joblog->setOutTime('23:00');
        $this->assertSame('2020-04-01 18:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-01 23:00', $joblog->getOut()->format('Y-m-d H:i'));
    }

    public function testMidnight(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        // And not?
        $start = new \DateTime('2020-04-02 00:00');
        $end = new \DateTime('2020-04-02 07:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $joblog->setInTime('00:00');
        $joblog->setOutTime('07:00');
        $this->assertSame('2020-04-02 00:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 07:00', $joblog->getOut()->format('Y-m-d H:i'));

        $joblog->setInTime('23:00');
        $joblog->setOutTime('07:00');
        $this->assertSame('2020-04-01 23:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-04-02 07:00', $joblog->getOut()->format('Y-m-d H:i'));
    }

    public function testExtraLongDay(): void
    {
        $joblog = new JobLog();
        $joblog->setJob($this->job);
        // And not?
        $start = new \DateTime('2020-05-09 09:00');
        $end = new \DateTime('2020-05-10 03:00');
        $this->shift->setStart($start);
        $this->shift->setEnd($end);
        $joblog->setInTime('09:00');
        $joblog->setOutTime('13:00');
        $this->assertSame('2020-05-09 09:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-05-09 13:00', $joblog->getOut()->format('Y-m-d H:i'));

        $joblog->setInTime('22:00');
        $joblog->setOutTime('01:00');
        $this->assertSame('2020-05-09 22:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-05-10 01:00', $joblog->getOut()->format('Y-m-d H:i'));

        $joblog->setInTime('01:00');
        $joblog->setOutTime('02:00');
        $this->assertSame('2020-05-10 01:00', $joblog->getIn()->format('Y-m-d H:i'));
        $this->assertSame('2020-05-10 02:00', $joblog->getOut()->format('Y-m-d H:i'));
    }
}

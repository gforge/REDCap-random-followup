<?php

use PHPUnit\Framework\TestCase;
use Gforge\RandomFollowup\Scheduler;

require_once __DIR__ . '/../Scheduler.php';

final class SchedulerTest extends TestCase
{
    public function test_parse_valid_date(): void
    {
        $dt = Scheduler::parse_redcap_date('2026-01-01');
        $this->assertNotNull($dt);
        $this->assertSame('2026-01-01', $dt->format('Y-m-d'));
    }

    public function test_parse_invalid_date(): void
    {
        $this->assertNull(Scheduler::parse_redcap_date('01/01/2026'));
    }

    public function test_feasibility_rule(): void
    {
        $this->assertFalse(
            Scheduler::is_feasible(
                n: 4,
                min_days: 60,
                max_days: 120,
                min_gap_days: 30
            )
        );
    }

    public function test_schedule_respects_gap(): void
    {
        $base = new DateTimeImmutable('2026-01-01');

        $out = Scheduler::schedule_binned(
            base_date: $base,
            min_days: 60,
            max_days: 180,
            min_gap_days: 30,
            target_fields: ['f1', 'f2', 'f3']
        );

        $this->assertNotNull($out);

        $dates = array_map(fn($d) => new DateTimeImmutable($d), array_values($out));
        sort($dates);

        for ($i = 1; $i < count($dates); $i++) {
            $this->assertGreaterThanOrEqual(
                30,
                $dates[$i]->diff($dates[$i - 1])->days
            );
        }
    }
}

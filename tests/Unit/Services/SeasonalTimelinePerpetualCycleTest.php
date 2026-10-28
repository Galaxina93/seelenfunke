<?php

namespace Tests\Unit\Services;

use App\Services\ConfiguratorService;
use Carbon\Carbon;
use Tests\TestCase;

class SeasonalTimelinePerpetualCycleTest extends TestCase
{
    public function test_animation_timeline_and_seasons_cycle_perpetually_across_multiple_years(): void
    {
        $service = app(ConfiguratorService::class);

        $day = Carbon::create(2026, 1, 1)->startOfDay();
        $end = Carbon::create(2030, 12, 31)->startOfDay();
        $daysSimulated = 0;

        while ($day->lte($end)) {
            $timeline = $service->getAnimationTimeline((int) $day->year, $day);

            $this->assertCount(15, $timeline, 'Expected 11 holiday events + 4 base seasons on ' . $day->toDateString());

            // Exakt 1 Element muss zu jedem beliebigen Kalendertag aktiv sein
            $activeItems = $timeline->where('is_active_now', true)->values();
            $this->assertCount(1, $activeItems, 'Expected exactly 1 active animation on ' . $day->toDateString());
            $this->assertTrue($timeline->first()['is_active_now'], 'First item must always be the active animation on ' . $day->toDateString());

            // Alle nachfolgenden (wartenden) Kacheln dürfen niemals in der Vergangenheit liegen
            // und müssen streng chronologisch aufsteigend sortiert sein
            $upcoming = $timeline->where('is_active_now', false)->values();
            for ($i = 0; $i < $upcoming->count(); $i++) {
                $item = $upcoming[$i];
                $this->assertFalse(
                    $item['event_date']->lt($day),
                    sprintf(
                        'Upcoming event %s (%s) is in the past on %s',
                        $item['event_key'],
                        $item['event_date']->toDateString(),
                        $day->toDateString()
                    )
                );

                if ($i > 0) {
                    $prev = $upcoming[$i - 1];
                    $this->assertFalse(
                        $item['event_date']->lt($prev['event_date']),
                        sprintf(
                            'Upcoming event %s (%s) is sorted after %s (%s) on %s',
                            $item['event_key'],
                            $item['event_date']->toDateString(),
                            $prev['event_key'],
                            $prev['event_date']->toDateString(),
                            $day->toDateString()
                        )
                    );
                }
            }

            $daysSimulated++;
            $day->addDay();
        }

        $this->assertSame(1826, $daysSimulated);
    }
}

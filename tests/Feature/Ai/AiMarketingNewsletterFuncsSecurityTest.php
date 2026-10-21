<?php

namespace Tests\Feature\Ai;

use App\Models\Marketing\MarketingNewsletter;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Services\AI\Functions\AiMarketingNewsletterFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMarketingNewsletterFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_subscribers_escapes_sql_wildcards()
    {
        $dummy = new class {
            use AiMarketingNewsletterFuncs;
        };

        MarketingNewsletterSubscriber::create([
            'email' => 'user_test@example.com',
            'is_verified' => true,
        ]);

        // Query with non-matching char where unescaped '_' would match
        $res = $dummy::marketing_newsletter_get_subscribers([
            'email_search' => 'userXtest',
        ]);
        $this->assertEquals(0, $res['count']);

        // Exact query
        $resExact = $dummy::marketing_newsletter_get_subscribers([
            'email_search' => 'user_test',
        ]);
        $this->assertEquals(1, $resExact['count']);
    }

    public function test_create_automated_campaign_clamps_days_offset_and_truncates()
    {
        $dummy = new class {
            use AiMarketingNewsletterFuncs;
        };

        $res = $dummy::marketing_newsletter_create_automated_campaign([
            'target_event_key' => 'valentines',
            'subject' => 'Überrasche deine Liebste zum Valentinstag',
            'content' => '<p>Schöne personalisierte Geschenke...</p>',
            'days_offset' => 9999, // Should clamp to 365
        ]);

        $this->assertEquals('success', $res['status']);
        $campaign = MarketingNewsletter::find($res['id']);
        $this->assertNotNull($campaign);
        $this->assertEquals(365, $campaign->days_offset);
        $this->assertEquals('valentines', $campaign->target_event_key);
        $this->assertTrue($campaign->is_active);

        // Toggle archive with string boolean representation
        $resToggle = $dummy::marketing_newsletter_toggle_archive([
            'id' => $campaign->id,
            'is_active' => 'false',
        ]);
        $this->assertEquals('success', $resToggle['status']);
        $campaign->refresh();
        $this->assertFalse($campaign->is_active);
    }
}

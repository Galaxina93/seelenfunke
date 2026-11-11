<?php

namespace Tests\Feature;

use App\Console\Commands\SyncMusicPlaylistCommand;
use App\Livewire\Shop\Management\ManagementMusic;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use App\Services\Management\MailAiSortService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FunkenwerkMusicAndMailSortTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $originalMailOrderJson = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\Admin\Admin::factory()->create(), 'admin');
        if (Storage::disk('local')->exists('funkenwerk_mail_order.json')) {
            $this->originalMailOrderJson = Storage::disk('local')->get('funkenwerk_mail_order.json');
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalMailOrderJson !== null) {
            Storage::disk('local')->put('funkenwerk_mail_order.json', $this->originalMailOrderJson);
        }
        parent::tearDown();
    }

    public function test_music_player_livewire_playlist_switching_syncs_tracks_and_updates_bottom_bar(): void
    {
        $playlistA = ManagementPlaylist::create([
            'name' => 'TestPlaylist Alpha',
            'description' => 'First playlist',
            'icon' => 'musical-note',
            'color' => '#C5A059',
            'sort_order' => 10,
        ]);

        $trackA = ManagementTrack::create([
            'playlist_id' => $playlistA->id,
            'title' => 'Only Time',
            'artist' => 'Bitshakerz',
            'duration_seconds' => 183,
            'file_path' => 'music/tracks/test_a.m4a',
            'mime_type' => 'audio/mp4',
            'file_size' => 2048000,
            'position' => 1,
            'is_favorite' => false,
            'play_count' => 0,
        ]);

        $playlistB = ManagementPlaylist::create([
            'name' => 'LifeIsGood Test',
            'description' => 'Second playlist',
            'icon' => 'musical-note',
            'color' => '#C5A059',
            'sort_order' => 20,
        ]);

        $trackB = ManagementTrack::create([
            'playlist_id' => $playlistB->id,
            'title' => 'Way Down We Go',
            'artist' => 'KALEO',
            'duration_seconds' => 214,
            'file_path' => 'music/tracks/test_b.m4a',
            'mime_type' => 'audio/mp4',
            'file_size' => 3048000,
            'position' => 1,
            'is_favorite' => false,
            'play_count' => 0,
        ]);

        $component = Livewire::test(ManagementMusic::class)
            ->call('selectPlaylist', $playlistA->id)
            ->assertSet('activePlaylistId', $playlistA->id)
            ->assertDispatched('music-view-tracks-synced');

        // Switch to second playlist (LifeIsGood Test) and verify tracks payload & inspector sync
        $component->call('selectPlaylist', $playlistB->id)
            ->assertSet('activePlaylistId', $playlistB->id)
            ->assertDispatched('music-view-tracks-synced', function (string $name, array $params) use ($trackB, $trackA) {
                $tracks = $params['tracks'] ?? [];
                $ids = array_column($tracks, 'id');
                return in_array($trackB->id, $ids, true) && !in_array($trackA->id, $ids, true);
            })
            ->call('selectTrackForEdit', $trackB->id)
            ->assertSet('selectedTrackId', $trackB->id)
            ->assertSet('editTitle', 'Way Down We Go')
            ->assertSet('editArtist', 'KALEO')
            ->assertSee('Way Down We Go')
            ->assertSee('KALEO');

        // Toggle favorite and verify sync
        $component->call('toggleFavorite', $trackB->id)
            ->assertDispatched('music-view-tracks-synced');
        $this->assertTrue((bool) $trackB->fresh()->is_favorite);
    }

    public function test_music_player_blade_view_uses_gold_vector_icons_and_wire_ignore_audio(): void
    {
        $bladeContent = file_get_contents(resource_path('views/livewire/shop/management/management-music.blade.php'));
        $backendLayoutContent = file_get_contents(resource_path('views/components/layouts/backend_layout.blade.php'));
        $globalPlayerContent = file_get_contents(resource_path('views/components/backend/global-music-player.blade.php'));

        $this->assertStringNotContainsString('🔀', $bladeContent, 'Old blue shuffle emoji must be removed');
        $this->assertStringNotContainsString('🔁', $bladeContent, 'Old blue repeat emoji must be removed');
        $this->assertStringContainsString('<x-backend.global-music-player />', $backendLayoutContent);
        $this->assertStringContainsString('id="funkenwerk-global-music-shell"', $globalPlayerContent);
        $this->assertStringContainsString('playTrackById(', $bladeContent);
        $this->assertStringContainsString('@music-view-tracks-synced.window', $bladeContent);
    }

    public function test_clean_pure_song_title_strips_parentheses_brackets_and_suffixes(): void
    {
        $cases = [
            'Only Time (Hardstyle)' => 'Only Time',
            'Where Is My Mind? (2007 Remaster)' => 'Where Is My Mind?',
            'Stereo Love (ambient Slowed)' => 'Stereo Love',
            'Sweater Weather (Slowed & Reverb) [Official Audio]' => 'Sweater Weather',
            'After Dark (Best Part Looped)' => 'After Dark',
            'Way Down We Go - Official Music Video' => 'Way Down We Go',
        ];

        foreach ($cases as $dirty => $expectedClean) {
            $this->assertSame(
                $expectedClean,
                SyncMusicPlaylistCommand::cleanPureSongTitle($dirty),
                "Failed cleaning title: {$dirty}"
            );
        }
    }

    public function test_ai_mail_sort_service_sorts_all_folders_rescues_misplaced_mails_and_orders_subfolders(): void
    {
        $account = MailAccount::create([
            'name' => 'AI Sort Test Account',
            'email' => 'aisort-test@mein-seelenfunke.de',
            'imap_host' => 'localhost',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'localhost',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'username' => 'aisort-test@mein-seelenfunke.de',
            'password' => 'secret',
            'status' => 'connected',
            'is_default' => false,
            'is_commercial' => false,
        ]);

        // 1. Normal PayPal receipt -> categorized as "PayPal & Zahlungen" and archived in "Archive"
        $paypalMsg = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => '<test-paypal-1@paypal.de>',
            'folder' => 'INBOX',
            'subject' => 'Sie haben eine Zahlung über 24,99 EUR an Spotify AB autorisiert',
            'from_name' => 'PayPal',
            'from_email' => 'service@paypal.de',
            'to_email' => $account->email,
            'body_plain' => 'Beleg für Ihre Zahlung an Spotify AB.',
            'is_read' => false,
            'is_archived' => false,
            'received_at' => now()->subHours(3),
        ]);

        // 2. Important Agentur für Arbeit email in Trash -> rescued to "Processed" ("Verarbeitet") with high priority
        $bundMsg = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => '<test-arbeitsagentur-1@arbeitsagentur.de>',
            'folder' => 'Trash',
            'subject' => 'Neue Nachricht in Ihrem Postfach der Bundesagentur für Arbeit',
            'from_name' => 'Bundesagentur für Arbeit',
            'from_email' => 'mitteilungsservice@arbeitsagentur.de',
            'to_email' => $account->email,
            'body_plain' => 'Bitte prüfen Sie Ihren Bescheid im Online-Portal.',
            'is_read' => false,
            'is_archived' => false,
            'received_at' => now()->subHours(2),
        ]);

        // 3. Routine successful server backup email in INBOX -> archived in "Archive" with low priority
        $backupMsg = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => '<test-backup-1@mein-seelenfunke.de>',
            'folder' => 'INBOX',
            'subject' => 'Backup erfolgreich: mein-seelenfunke Datenbank-Sicherung',
            'from_name' => 'Seelenfunke Server',
            'from_email' => 'kontakt@mein-seelenfunke.de',
            'to_email' => $account->email,
            'body_plain' => 'Automatischer mysqldump erfolgreich abgeschlossen.',
            'is_read' => false,
            'is_archived' => false,
            'received_at' => now()->subHour(),
        ]);

        // 4. Legacy AI folder -> must be dissolved
        MailFolder::create([
            'mail_account_id' => $account->id,
            'name' => 'Paypal',
        ]);

        // 5. False-positive spam rule on paypal.de -> must be removed
        MailRule::create([
            'mail_account_id' => $account->id,
            'condition_field' => 'from_email',
            'condition_value' => 'paypal.de',
            'action' => 'mark_spam',
            'type' => 'spam',
        ]);

        $service = new MailAiSortService();
        $result = $service->sortAllMailsAndSubfolders();

        $this->assertTrue($result['success']);
        $this->assertSame('Archive', $paypalMsg->fresh()->folder);
        $this->assertTrue((bool) $paypalMsg->fresh()->is_archived);
        $this->assertSame('Bank & Finanzen', $paypalMsg->fresh()->category);

        $this->assertSame('Processed', $bundMsg->fresh()->folder);
        $this->assertFalse((bool) $bundMsg->fresh()->is_archived);
        $this->assertSame('high', $bundMsg->fresh()->priority);
        $this->assertSame('Behörde', $bundMsg->fresh()->category);

        $this->assertSame('Archive', $backupMsg->fresh()->folder);
        $this->assertTrue((bool) $backupMsg->fresh()->is_archived);
        $this->assertSame('low', $backupMsg->fresh()->priority);

        $this->assertDatabaseMissing('mail_folders', [
            'mail_account_id' => $account->id,
            'name' => 'Paypal',
        ]);

        $this->assertDatabaseMissing('mail_rules', [
            'mail_account_id' => $account->id,
            'condition_value' => 'paypal.de',
            'action' => 'mark_spam',
        ]);
    }
}

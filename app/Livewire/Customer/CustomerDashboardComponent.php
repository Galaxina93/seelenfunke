<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Order\OrderOrder;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.customer_layout')]
class CustomerDashboardComponent extends Component
{
    #[Locked]
    public $profileSteps = [];
    #[Locked]
    public $recentOrders = [];

    #[Locked]
    public bool $isGameModeActive = false;

    #[Locked]
    public bool $hasActivatedGamification = false;

    public bool $acceptTerms = false;
    #[Locked]
    public $activeGiftVouchers = [];

    #[Locked]
    public string $referralCode = '';

    #[Locked]
    public bool $hasCompletedOrder = false;

    #[Locked]
    public int $successfulReferralsCount = 0;

    #[Locked]
    public int $totalReferralEarnedCents = 0;

    // Seelenfunken-Treueprogramm & Funki (Level 1-10) – serverseitig gesperrt gegen Manipulation
    #[Locked]
    public int $funkiLevel = 1;

    #[Locked]
    public int $displayFunkiLevel = 1;

    #[Locked]
    public int $funkenBalance = 0;

    #[Locked]
    public int $funkenTotalEarned = 0;

    #[Locked]
    public int $progressPercentage = 0;

    #[Locked]
    public int $missingSparks = 150;

    #[Locked]
    public bool $isMaxLevel = false;

    #[Locked]
    public string $epBottomArcText = '';

    #[Locked]
    public array $currentLevelConfig = [];

    #[Locked]
    public array $allFunkiLevels = [];

    #[Locked]
    public array $redeemableVouchers = [];

    #[Locked]
    public array $customerVouchers = [];

    #[Locked]
    public array $titlesData = [];

    #[Locked]
    public ?string $currentRankName = null;

    #[Locked]
    public ?string $activeTitleKey = null;

    // Halle der Helden (DSGVO Opt-In, Spielername vs. Echtname & Paginierung)
    public bool $rankingOptIn = false;
    public bool $useRealName = false;
    public string $playerName = '';
    public ?string $playerNameError = null;
    public ?string $hofFeedbackMessage = null;
    public bool $hofFeedbackSuccess = true;

    // Jährliche Treue-Funken (Geburtstag & Manufaktur-Jahrestag)
    public string $customerBirthdayInput = '';
    public ?string $birthdayFeedbackMessage = null;
    public bool $birthdayFeedbackSuccess = true;

    #[Locked]
    public array $loyaltyMilestones = [];

    #[Locked]
    public string $heroDisplayName = '';

    #[Locked]
    public array $hallOfFameLeaders = [];

    #[Locked]
    public int $hallOfFamePage = 1;

    #[Locked]
    public int $hallOfFamePerPage = 10;

    #[Locked]
    public int $hallOfFameLastPage = 1;

    #[Locked]
    public int $hallOfFameTotal = 0;

    // Audio-Einstellungen (BGM bgm_funki_game & UI-Sounds)
    public int $funkiAudioVolume = 35;
    public bool $funkiAudioMuted = true;
    public bool $funkiAudioKeepPlaying = false;

    public function boot(): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !empty($user->needs_password_change)) {
            abort(403);
        }
    }

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->loadDashboardData($user);
    }

    #[On('profile-updated')]
    #[On('sparks-awarded')]
    #[On('gamification-mode-changed')]
    public function refreshData(): void
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $user->refresh();
            $user->load('profile');
            $this->loadDashboardData($user);
        }
    }

    public function activateSeelenfunke(GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $this->acceptTerms = true;
        $profile = $gameService->getProfile($user);
        $wasActive = (bool) $profile->is_active;
        $profile->is_active = true;

        $progress = is_array($profile->titles_progress) ? $profile->titles_progress : [];
        $progress['_audio_muted'] = true;
        $progress['_audio_keep_playing'] = false;
        $profile->titles_progress = $progress;

        $gameService->syncLevelFromLifetimeSparks($profile);
        $profile->save();

        if (!$wasActive) {
            $gameService->recordUserProfileChangeLog($user, [
                'gamification_active' => ['old' => false, 'new' => true],
            ], 'Kunde (im Frontend)');
        }

        $gameService->logGamificationEvent(
            (string) $user->id,
            'opt_in',
            'system',
            'Seelenfunken-Magie aktiviert',
            'Kunde hat das Seelenfunken-Sammelspiel im Dashboard aktiviert.',
            0,
            (int) $profile->funken_balance
        );

        $this->loadDashboardData($user);
        $this->dispatch('gamification-mode-changed');
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Die Magie ist erwacht! Dein Begleiter Funki & die Seelenfunken-Suche sind jetzt aktiv. ✨',
        ]);
    }

    public function activateGamification(GamificationService $gameService): void
    {
        $this->activateSeelenfunke($gameService);
    }

    public function deactivateSeelenfunke(GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $profile = $gameService->getProfile($user);
        $wasActive = (bool) $profile->is_active;
        $profile->is_active = false;
        $profile->save();

        if ($wasActive) {
            $gameService->recordUserProfileChangeLog($user, [
                'gamification_active' => ['old' => true, 'new' => false],
            ], 'Kunde (im Frontend)');
        }

        $this->acceptTerms = false;
        $this->loadDashboardData($user);
        $this->dispatch('gamification-mode-changed');
        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Seelenfunken-Sammeln deaktiviert. Dein bisheriger Fortschritt, dein Funki-Level & deine Funken bleiben 100 % sicher gespeichert – du kannst die Magie jederzeit wieder aktivieren!',
        ]);
    }

    public function toggleSparkHunt(GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $profile = $gameService->getProfile($user);
        if ($profile->is_active) {
            $this->deactivateSeelenfunke($gameService);
        } else {
            $this->activateSeelenfunke($gameService);
        }
    }

    public function saveFunkiAudioSettings(int $volumePercent, bool $isMuted, ?bool $keepPlaying = null): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $gameService = app(GamificationService::class);
        $res = $gameService->updateAudioSettings($user, $volumePercent, $isMuted, $keepPlaying);
        $this->funkiAudioVolume = (int) ($res['volume'] ?? 35);
        $this->funkiAudioMuted = (bool) ($res['muted'] ?? true);
        $this->funkiAudioKeepPlaying = (bool) ($res['keep_playing'] ?? false);
    }

    public function nextHallOfFamePage(): void
    {
        if ($this->hallOfFamePage < $this->hallOfFameLastPage) {
            $this->hallOfFamePage++;
            $user = Auth::guard('customer')->user();
            if ($user) {
                $this->loadHallOfFamePage(app(GamificationService::class));
            }
        }
    }

    public function prevHallOfFamePage(): void
    {
        if ($this->hallOfFamePage > 1) {
            $this->hallOfFamePage--;
            $user = Auth::guard('customer')->user();
            if ($user) {
                $this->loadHallOfFamePage(app(GamificationService::class));
            }
        }
    }

    public function gotoHallOfFamePage(int $page): void
    {
        $this->hallOfFamePage = max(1, min($this->hallOfFameLastPage, $page));
        $user = Auth::guard('customer')->user();
        if ($user) {
            $this->loadHallOfFamePage(app(GamificationService::class));
        }
    }

    protected function loadHallOfFamePage(GamificationService $gameService): void
    {
        $paginated = $gameService->getHallOfFamePaginated($this->hallOfFamePage, $this->hallOfFamePerPage);
        $this->hallOfFameLeaders = $paginated['items'];
        $this->hallOfFamePage = (int) $paginated['current_page'];
        $this->hallOfFameLastPage = (int) $paginated['last_page'];
        $this->hallOfFameTotal = (int) $paginated['total'];
    }

    public function redeemVoucher(string $voucherKey, GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $res = $gameService->redeemSparksForVoucher($user, $voucherKey);
        $this->loadDashboardData($user);

        if (!empty($res['success'])) {
            $this->dispatch('sparks-awarded');
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => $res['message'] ?? 'Gutschein erfolgreich freigeschaltet!',
            ]);
        } else {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => $res['message'] ?? 'Gutschein konnte nicht eingelöst werden.',
            ]);
        }
    }

    public function redeemFunkenVoucher(string $voucherKey, GamificationService $gameService): void
    {
        $this->redeemVoucher($voucherKey, $gameService);
    }

    public function selectFunkiStage(int $stageLevel, GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $profile = $gameService->getProfile($user);
        $maxUnlocked = min(GameConfig::MAX_LEVEL, max(1, (int) $profile->level));

        if ($stageLevel < 1 || $stageLevel > $maxUnlocked) {
            $this->dispatch('notify', [
                'type'    => 'error',
                'message' => 'Diese Funki-Stufe hast du noch nicht freigeschaltet.',
            ]);
            return;
        }

        $profile->active_background = $stageLevel === $maxUnlocked ? 'default' : ('stage_' . $stageLevel);
        $profile->save();

        $this->loadDashboardData($user);
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Funki Stufe ' . $stageLevel . ' & Hintergrund als Profilbild ausgewählt! ✨',
        ]);
    }

    public function selectTitle(?string $titleKey, GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $res = $gameService->selectCustomerTitle($user, $titleKey);
        $this->loadDashboardData($user);

        $this->dispatch('notify', [
            'type'    => !empty($res['success']) ? 'success' : 'error',
            'message' => $res['message'] ?? 'Titel aktualisiert.',
        ]);
    }

    public function saveHallOfFameSettings(GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        if ($this->rankingOptIn && !$this->useRealName && trim((string) $this->playerName) === '') {
            $this->rankingOptIn = false;
            $msg = 'Bitte lege zuerst deinen Spielernamen fest und klicke auf Speichern (oder wähle Echtname), bevor du die Ranglisten-Anzeige aktivierst.';
            $this->playerNameError = $msg;
            $this->addError('playerName', $msg);
            return;
        }

        $this->playerNameError = null;
        $this->hofFeedbackMessage = null;

        $res = $gameService->updateHallOfFameSettings(
            $user,
            $this->rankingOptIn,
            $this->useRealName,
            $this->playerName
        );

        if (!empty($res['success'])) {
            $this->loadDashboardData($user);
            $this->hofFeedbackSuccess = true;
            $this->hofFeedbackMessage = $res['message'] ?? 'Deine Halle der Helden Einstellungen wurden erfolgreich gespeichert! ✨';
        } else {
            $this->playerNameError = $res['message'] ?? 'Dieser Spielername ist nicht zulässig.';
            $this->hofFeedbackSuccess = false;
            $this->hofFeedbackMessage = $this->playerNameError;
        }

        $this->dispatch('notify', [
            'type'    => !empty($res['success']) ? 'success' : 'error',
            'message' => $res['message'] ?? 'Halle der Helden Einstellungen gespeichert.',
        ]);
    }

    public function updatedRankingOptIn(): void
    {
        if ($this->rankingOptIn && !$this->useRealName && trim((string) $this->playerName) === '') {
            $this->rankingOptIn = false;
            $msg = 'Bitte lege zuerst deinen Spielernamen fest und klicke auf Speichern (oder wähle Echtname), bevor du die Ranglisten-Anzeige aktivierst.';
            $this->playerNameError = $msg;
            $this->addError('playerName', $msg);
            return;
        }

        $this->resetErrorBag('playerName');
        $this->saveHallOfFameSettings(app(GamificationService::class));
    }

    public function saveCustomerBirthday(GamificationService $gameService): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return;
        }

        $this->birthdayFeedbackMessage = null;
        $res = $gameService->updateCustomerBirthday($user, $this->customerBirthdayInput);

        if (!empty($res['success'])) {
            $user->refresh();
            $user->load('profile');
            $this->loadDashboardData($user);
            $this->birthdayFeedbackSuccess = true;
            $this->birthdayFeedbackMessage = $res['message'] ?? 'Geburtsdatum gespeichert!';
            if (!empty($res['birthday_awarded'])) {
                $this->dispatch('sparks-awarded');
            }
        } else {
            $this->birthdayFeedbackSuccess = false;
            $this->birthdayFeedbackMessage = $res['message'] ?? 'Geburtsdatum konnte nicht gespeichert werden.';
        }

        $this->dispatch('notify', [
            'type'    => !empty($res['success']) ? 'success' : 'error',
            'message' => $this->birthdayFeedbackMessage,
        ]);
    }

    protected function loadDashboardData($user): void
    {
        $this->checkProfileSteps($user);

        $this->recentOrders = OrderOrder::where('customer_id', $user->id)
            ->latest()
            ->take(3)
            ->get();

        $this->activeGiftVouchers = $user->hasVerifiedEmail()
            ? MarketingGiftVoucher::where('recipient_email', $user->email)
                ->where('is_active', true)
                ->where('current_balance', '>', 0)
                ->get()
            : collect();

        $this->hasCompletedOrder = OrderOrder::where('customer_id', $user->id)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->exists();

        // Seelenfunken & Funki-Daten laden
        $gameService = app(GamificationService::class);
        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        if (!$profile && $this->hasCompletedOrder) {
            $profile = $gameService->getProfile($user);
        }

        // Jährliche Treue-Funken (Geburtstag & Manufaktur-Jahrestag) automatisch prüfen
        if ($profile && $profile->is_active) {
            $loyaltyAwarded = $gameService->processAnnualLoyaltySparksForCustomer($user, true);
            if (!empty($loyaltyAwarded['birthday_awarded']) || !empty($loyaltyAwarded['anniversary_awarded'])) {
                $profile->refresh();
            }
        }

        $this->loyaltyMilestones = $gameService->getLoyaltyMilestonesStatus($user, $profile);
        $this->customerBirthdayInput = (string) ($this->loyaltyMilestones['birthday'] ?? '');

        $this->allFunkiLevels = GameConfig::getFunkiLevels();
        $this->redeemableVouchers = GameConfig::getRedeemableVouchers();
        $this->customerVouchers = $gameService->getCustomerActiveVouchers($user);

        if ($profile) {
            $gameService->syncLevelFromLifetimeSparks($profile);
            if ($profile->isDirty('level')) {
                $profile->save();
            }

            $this->isGameModeActive = (bool) $profile->is_active;
            $this->hasActivatedGamification = $this->isGameModeActive;
            $this->funkenBalance = max(0, (int) $profile->funken_balance);
            $this->funkenTotalEarned = max(0, (int) $profile->funken_total_earned);

            $progressData = GameConfig::getLevelProgressData($this->funkenTotalEarned, (int) ($profile->level ?: 1));
            $this->funkiLevel = (int) $progressData['level'];
            $this->isMaxLevel = (bool) $progressData['is_max_level'];
            $this->progressPercentage = (int) $progressData['progress_percentage'];
            $this->missingSparks = (int) $progressData['missing_sparks'];
            $this->epBottomArcText = (string) $progressData['ep_bottom_arc_text'];

            // Prüfen, ob der Kunde eine bestimmte bereits freigeschaltete Stufe (1..funkiLevel) gewählt hat
            $activeBg = (string) ($profile->active_background ?? 'default');
            if (str_starts_with($activeBg, 'stage_')) {
                $chosenStage = (int) str_replace('stage_', '', $activeBg);
                $this->displayFunkiLevel = ($chosenStage >= 1 && $chosenStage <= $this->funkiLevel)
                    ? $chosenStage
                    : $this->funkiLevel;
            } else {
                $this->displayFunkiLevel = $this->funkiLevel;
            }

            $this->currentLevelConfig = GameConfig::getLevelConfig($this->displayFunkiLevel);
            $this->titlesData = $gameService->evaluateTitles($profile);
            $this->activeTitleKey = $profile->active_title ? (string) $profile->active_title : null;
            $this->currentRankName = $gameService->resolveActiveRankName($profile, $this->titlesData);
            $this->rankingOptIn = (bool) $profile->ranking_opt_in;
            $this->useRealName = (bool) $profile->use_real_name;
            $this->playerName = (string) ($profile->player_name ?? '');

            $audioSettings = $gameService->getAudioSettings($profile);
            $this->funkiAudioVolume = (int) $audioSettings['volume'];
            $this->funkiAudioMuted = (bool) $audioSettings['muted'];
            $this->funkiAudioKeepPlaying = (bool) ($audioSettings['keep_playing'] ?? false);

            if (!$this->useRealName && trim($this->playerName) !== '') {
                $this->heroDisplayName = trim($this->playerName);
            } else {
                $this->heroDisplayName = trim($user->first_name . ' ' . $user->last_name) ?: ($user->first_name ?: 'Seelenfunke-Held');
            }
        } else {
            $this->isGameModeActive = false;
            $this->funkiLevel = 1;
            $this->displayFunkiLevel = 1;
            $this->funkenBalance = 0;
            $this->funkenTotalEarned = 0;
            $progressData = GameConfig::getLevelProgressData(0, 1);
            $this->isMaxLevel = false;
            $this->progressPercentage = 0;
            $this->missingSparks = (int) $progressData['missing_sparks'];
            $this->epBottomArcText = (string) $progressData['ep_bottom_arc_text'];
            $this->currentLevelConfig = GameConfig::getLevelConfig(1);
            $this->titlesData = [
                'titles'          => [],
                'level_titles'    => GameConfig::getLevelTitles(),
                'current_level'   => 1,
                'diamonds_count'  => 0,
                'diamond_count'   => 0,
                'total_titles'    => count(GameConfig::getTitles()),
                'mega_title'      => null,
                'next_mega_title' => GameConfig::getMegaTitles()[0] ?? null,
                'is_seelengott'   => false,
            ];
            $this->activeTitleKey = null;
            $this->currentRankName = null;
            $this->rankingOptIn = false;
            $this->useRealName = false;
            $this->playerName = '';
            $this->funkiAudioVolume = 35;
            $this->funkiAudioMuted = true;
            $this->funkiAudioKeepPlaying = false;
            $this->heroDisplayName = trim($user->first_name . ' ' . $user->last_name) ?: ($user->first_name ?: 'Seelenfunke-Held');
        }

        $this->loadHallOfFamePage($gameService);

        if ($this->hasCompletedOrder) {
            try {
                $ref = app(ReferralService::class)->getOrCreateReferralForCustomer(
                    $user->email,
                    $user->first_name,
                    $user->last_name,
                    $user->id
                );
                $this->referralCode = $ref->code;
                $this->successfulReferralsCount = (int) $ref->successful_referrals_count;
                $this->totalReferralEarnedCents = $this->successfulReferralsCount * (int) ($ref->reward_cents ?? 500);
            } catch (\Exception $e) {
                Log::error('Dashboard referral resolution error: ' . $e->getMessage());
                $this->referralCode = 'FREUNDE10';
                $this->successfulReferralsCount = 0;
                $this->totalReferralEarnedCents = 0;
            }
        }
    }

    protected function checkProfileSteps($user): void
    {
        $this->profileSteps = [];
        if (!$user->profile) {
            return;
        }
        $p = $user->profile;

        $needsProfileInfo = empty($user->first_name) || empty($user->last_name) ||
            empty($p->street) || empty($p->city) || empty($p->house_number) || empty($p->postal);

        if ($needsProfileInfo) {
            $this->profileSteps[] = ['label' => 'Profil Informationen', 'action' => "\$dispatch('open-profile-modal', {tab: 'profile'})"];
        }
    }

    public function render()
    {
        return view('livewire.customer.customer-dashboard-component');
    }
}

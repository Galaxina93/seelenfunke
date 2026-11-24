<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Product\LaserStudioProject;
use App\Models\Product\Product;
use App\Services\AI\AiAuthHelper;
use App\Services\ConfiguratorService;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\LaserPhotoPreprocessor;
use App\Services\Laser\LaserQrCodeService;
use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class LaserStudioWorkspace extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Produkte';

    // =========================================================================
    // SCHRITT 1: PROJEKTVERWALTUNG (ANALOG XTOOL PROJEKTÜBERSICHT)
    // =========================================================================
    public string $projectFilter = 'active'; // active | favorites | archived
    public string $projectSearch = '';
    public bool $showNewProjectModal = false;
    public string $newProjectPreset = 'card_86x54'; // card_86x54 | free_bed | square_50x50 | product_blank
    public string $newProjectTitle = '';
    public float $newProjectWidthMm = 86.0;
    public float $newProjectHeightMm = 54.0;
    public bool $newProjectTwoSided = true;
    public ?string $newProjectProductId = null;

    // =========================================================================
    // SCHRITT 2: AKTIVES LASER-PROJEKT (KONFIGURATOR + LIVE-KAMERA + COCKPIT)
    // =========================================================================
    #[Locked]
    public ?string $activeProjectId = null;

    public string $projectTitle = '';
    public string $projectMode = 'custom_card';
    public ?string $projectProductId = null;
    public float $projectWidthMm = 86.0;
    public float $projectHeightMm = 54.0;
    public string $projectContourShape = 'rounded_card'; // rounded_card | rect | circle
    public bool $projectHasBackSide = false;
    public string $surfaceTone = 'dark_card'; // dark_card | gold_card | light_surface
    public string $activeSide = 'front'; // front | back

    // Werkstück-Lage auf dem 200x200 mm xTool-Kamerabett
    public float $centerXMm = 100.0;
    public float $centerYMm = 100.0;
    public float $angleDeg = 0.0;
    public string $framingMode = 'contour'; // contour | rect
    public string $cameraLightMode = 'normal'; // normal | dim | anti_glare
    public string $cameraZoomMode = 'bed'; // bed | focus
    public string $cameraUrl = '';
    public bool $laserOnline = false;
    public string $cameraUpdatedAt = '';
    public int $workflowStep = 1; // 1 = Kamera & Ausrichten | 2 = Bereit für Laser | 3 = Gesendet
    public bool $isProjectSaved = false;

    // Konfigurator-Ebenen (100% kompatibel mit ProductConfigurator & FileDownloadService)
    public string $activeToolTab = 'text'; // text | qr | symbols | upload
    public array $texts = [];
    public array $logos = [];
    public array $textsBack = [];
    public array $logosBack = [];
    public ?int $selectedTextIdx = null;
    public ?int $selectedLogoIdx = null;

    // QR-Code-Generator Eingaben
    public string $qrTargetUrl = 'https://mein-seelenfunke.de/links';
    public string $qrSubtitle = '';

    // Symbol- & Spruch-Auswahl
    public string $selectedVectorCategory = '';
    public string $selectedQuoteCategory = '';
    public $studioUploadFile = null;

    // Laser-Cockpit Parameter (analog Schritt 1 · In Bearbeitung)
    public string $selectedStudioPresetId = 'builtin_card_black_alu';
    public string $newCustomPresetName = '';
    public array $laserParams = [
        'equipment_key' => 'flatbed_standard',
        'process_mode' => 'Prozess auf flacher Oberfläche',
        'material' => 'Eloxiertes Aluminium',
        'focus_mm' => 0.5,
        'element_type' => 'Bild',
        'engrave_tab' => 'Gravieren',
        'preset_mode' => 'Benutzerdefiniert',
        'power_percent' => 65,
        'speed_mms' => 350,
        'dpi' => 500,
        'dot_duration_us' => 90,
        'frequency_khz' => 60,
        'passes' => 1,
        'bitmap_mode' => 'Schwellenwert',
        'engrave_mode' => 'Bidirektional',
        'vector_note' => '',
    ];

    public ?string $statusNotice = null;
    public string $statusNoticeType = 'success'; // success | warning | error

    // =========================================================================
    // 60-FPS LASER-FOTO-STUDIO & DATEIUPLOAD-VERWALTUNG (ANALOG BESTELLUNG SCHRITT 1)
    // =========================================================================
    public bool $isLaserPhotoStudioOpen = false;
    public ?string $photoStudioItemId = null;
    public ?string $photoStudioFileKey = null;
    public ?string $photoStudioFileName = null;
    public ?string $photoStudioOriginalRelPath = null;
    public ?string $photoStudioOriginalUrl = null;
    public ?string $photoStudioPreviewUrl = null;
    public bool $photoStudioIsDarkSurface = true;
    public int $photoStudioDpi = 600;
    public string $photoStudioMaterialHint = '';
    public array $photoStudioProfiles = [];
    public string $photoStudioActiveProfileId = '';
    public array $photoStudioParams = [];
    public int $uploadedFilesRevision = 0;

    public function mount(): void
    {
        $this->assertStaffAccess();
        $vectors = $this->availableVectors;
        if (!empty($vectors)) {
            $this->selectedVectorCategory = (string) array_key_first($vectors);
        }
        $quotes = $this->availableQuotes;
        if (!empty($quotes)) {
            $this->selectedQuoteCategory = (string) array_key_first($quotes);
        }
        $this->hydrateCachedCameraState();
    }

    protected function assertStaffAccess(): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    #[Computed]
    public function projects()
    {
        if (!Schema::hasTable('laser_studio_projects')) {
            return collect();
        }

        $q = LaserStudioProject::with('product:id,name,slug');

        if ($this->projectFilter === 'favorites') {
            $q->where('status', 'active')->where('is_favorite', true);
        } elseif ($this->projectFilter === 'archived') {
            $q->where('status', 'archived');
        } else {
            $q->where('status', 'active');
        }

        $term = trim($this->projectSearch);
        if ($term !== '') {
            $esc = addcslashes($term, '%_\\');
            $q->where('title', 'like', "%{$esc}%");
        }

        return $q->orderByDesc('is_favorite')->orderByDesc('updated_at')->limit(60)->get();
    }

    #[Computed]
    public function availableFonts(): array
    {
        return app(ConfiguratorService::class)->getFonts();
    }

    #[Computed]
    public function availableVectors(): array
    {
        return app(ConfiguratorService::class)->getStandardVectors();
    }

    #[Computed]
    public function availableQuotes(): array
    {
        return app(ConfiguratorService::class)->getStandardQuotes();
    }

    #[Computed]
    public function physicalProducts()
    {
        if (!Schema::hasTable('products')) {
            return collect();
        }

        return Product::where('status', 'active')
            ->where('type', 'physical')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'configurator_settings']);
    }

    #[Computed]
    public function studioPresets(): array
    {
        return LaserQrCodeService::getAllStudioMaterialPresets();
    }

    #[Computed]
    public function equipmentModes(): array
    {
        return XtoolLaserCockpitService::getSupportedEquipmentModes();
    }

    #[Computed]
    public function quickLinks(): array
    {
        return LaserQrCodeService::getQuickLinkTemplates();
    }

    // =========================================================================
    // PROJEKT-LEBENSZYKLUS (ANLEGEN, ÖFFNEN, DUPLIZIEREN, ARCHIVIEREN, LÖSCHEN)
    // =========================================================================

    public function selectNewProjectPreset(string $preset): void
    {
        $this->assertStaffAccess();
        $this->newProjectPreset = $preset;
        if ($preset === 'card_86x54') {
            $this->newProjectWidthMm = 86.0;
            $this->newProjectHeightMm = 54.0;
            $this->newProjectTwoSided = true;
        } elseif ($preset === 'free_bed') {
            $this->newProjectWidthMm = 200.0;
            $this->newProjectHeightMm = 200.0;
            $this->newProjectTwoSided = false;
        } elseif ($preset === 'square_50x50') {
            $this->newProjectWidthMm = 50.0;
            $this->newProjectHeightMm = 50.0;
            $this->newProjectTwoSided = false;
        }
    }

    public function createProject(?string $quickPreset = null): void
    {
        $this->assertStaffAccess();
        if (!Schema::hasTable('laser_studio_projects')) {
            return;
        }

        $preset = $quickPreset ?? $this->newProjectPreset;
        $mode = 'custom_card';
        $width = max(5.0, min(200.0, (float) $this->newProjectWidthMm));
        $height = max(5.0, min(200.0, (float) $this->newProjectHeightMm));
        $shape = 'rounded_card';
        $twoSided = (bool) $this->newProjectTwoSided;
        $productId = null;
        $defaultTitle = trim($this->newProjectTitle);

        if ($preset === 'card_86x54') {
            $width = 86.0;
            $height = 54.0;
            $shape = 'rounded_card';
            $twoSided = $quickPreset !== null ? true : $twoSided;
            if ($defaultTitle === '') {
                $defaultTitle = 'Geschenk- & QR-Karte ' . now()->format('d.m. H:i');
            }
        } elseif ($preset === 'free_bed') {
            $mode = 'free_bed';
            $width = 200.0;
            $height = 200.0;
            $shape = 'rect';
            $twoSided = false;
            if ($defaultTitle === '') {
                $defaultTitle = 'Freies Laser-Unikat ' . now()->format('d.m. H:i');
            }
        } elseif ($preset === 'square_50x50') {
            $width = 50.0;
            $height = 50.0;
            $shape = 'rect';
            if ($defaultTitle === '') {
                $defaultTitle = 'Laser-Projekt 50×50 mm';
            }
        } elseif ($preset === 'product_blank' && $this->newProjectProductId) {
            $prod = Product::find($this->newProjectProductId);
            if ($prod) {
                $mode = 'product_blank';
                $productId = (string) $prod->id;
                $cal = app(FileDownloadService::class)->resolvePhysicalBlankCalibration($prod);
                $width = (float) ($cal['widthMm'] ?? 45.0);
                $height = (float) ($cal['heightMm'] ?? 45.0);
                $shape = (string) ($cal['contourShape'] ?? 'rect');
                if ($defaultTitle === '') {
                    $defaultTitle = $prod->name . ' · Studio';
                }
            }
        }

        if ($defaultTitle === '') {
            $defaultTitle = 'Laser-Projekt ' . now()->format('d.m.Y H:i');
        }

        $defaultPreset = LaserQrCodeService::getAllStudioMaterialPresets()[0] ?? null;
        $initParams = is_array($defaultPreset['params'] ?? null) ? $defaultPreset['params'] : $this->laserParams;

        $project = new LaserStudioProject([
            'title' => mb_substr($defaultTitle, 0, 180),
            'status' => 'active',
            'is_favorite' => false,
            'mode' => $mode,
            'product_id' => $productId,
            'width_mm' => $width,
            'height_mm' => $height,
            'contour_shape' => $shape,
            'has_back_side' => $twoSided,
            'surface_tone' => 'dark_card',
            'configuration' => [
                'texts' => [],
                'logos' => [],
                'texts_back' => [],
                'logos_back' => [],
                'laser_cockpit_coordinates' => [
                    'front' => ['center_x_mm' => 100.0, 'center_y_mm' => 100.0, 'angle_deg' => 0.0, 'width_mm' => $width, 'height_mm' => $height],
                    'back' => ['center_x_mm' => 100.0, 'center_y_mm' => 100.0, 'angle_deg' => 0.0, 'width_mm' => $width, 'height_mm' => $height],
                ],
                'laser_cockpit_parameters' => [
                    'front' => $initParams,
                    'back' => $initParams,
                ],
                'laser_parameter_overrides' => [
                    'front' => $initParams,
                    'back' => $initParams,
                ],
            ],
        ]);
        $project->syncStudioDimensionsToConfig();
        $project->save();

        $this->showNewProjectModal = false;
        $this->newProjectTitle = '';
        unset($this->projects);

        $this->openProject((string) $project->id);
    }

    public function openProject(string $projectId): void
    {
        $this->assertStaffAccess();
        $project = LaserStudioProject::with('product')->find($projectId);
        if (!$project) {
            return;
        }

        $this->activeProjectId = (string) $project->id;
        $this->projectTitle = (string) $project->title;
        $this->projectMode = (string) $project->mode;
        $this->projectProductId = $project->product_id ? (string) $project->product_id : null;
        $this->projectWidthMm = (float) $project->width_mm;
        $this->projectHeightMm = (float) $project->height_mm;
        $this->projectContourShape = (string) ($project->contour_shape ?: 'rect');
        $this->projectHasBackSide = (bool) $project->has_back_side;
        $this->surfaceTone = (string) ($project->surface_tone ?: 'dark_card');
        $this->activeSide = 'front';
        $this->workflowStep = 1;
        $this->statusNotice = null;

        $conf = is_array($project->configuration) ? $project->configuration : [];
        $this->texts = array_values(is_array($conf['texts'] ?? null) ? $conf['texts'] : []);
        $this->logos = array_values(is_array($conf['logos'] ?? null) ? $conf['logos'] : []);
        $this->textsBack = array_values(is_array($conf['texts_back'] ?? null) ? $conf['texts_back'] : []);
        $this->logosBack = array_values(is_array($conf['logos_back'] ?? null) ? $conf['logos_back'] : []);

        $hasAnyElements = !empty($this->texts) || !empty($this->logos) || !empty($this->textsBack) || !empty($this->logosBack);
        $this->isProjectSaved = $hasAnyElements && empty($conf['has_unsaved_freehand_changes']);

        $this->selectedTextIdx = !empty($this->texts) ? 0 : null;
        $this->selectedLogoIdx = null;

        $this->loadSideCoordinatesAndParams($project, 'front');
        $this->hydrateCachedCameraState();
    }

    public function closeProject(): void
    {
        if ($this->activeProjectId) {
            $this->saveMetadataOnly(silent: true);
        }
        $this->activeProjectId = null;
        $this->statusNotice = null;
        unset($this->projects);
    }

    public function duplicateProject(string $projectId): void
    {
        $this->assertStaffAccess();
        $orig = LaserStudioProject::find($projectId);
        if (!$orig) {
            return;
        }

        $copy = $orig->replicate();
        $copy->title = mb_substr($orig->title . ' (Kopie)', 0, 180);
        $copy->status = 'active';
        $copy->last_lasered_at = null;
        $copy->save();
        unset($this->projects);
    }

    public function toggleFavoriteProject(string $projectId): void
    {
        $this->assertStaffAccess();
        $proj = LaserStudioProject::find($projectId);
        if ($proj) {
            $proj->update(['is_favorite' => !$proj->is_favorite]);
            unset($this->projects);
        }
    }

    public function toggleArchiveProject(string $projectId): void
    {
        $this->assertStaffAccess();
        $proj = LaserStudioProject::find($projectId);
        if ($proj) {
            $next = $proj->status === 'archived' ? 'active' : 'archived';
            $proj->update(['status' => $next]);
            if ($this->activeProjectId === $projectId && $next === 'archived') {
                $this->activeProjectId = null;
            }
            unset($this->projects);
        }
    }

    public function deleteProject(string $projectId): void
    {
        $this->assertStaffAccess();
        $proj = LaserStudioProject::find($projectId);
        if ($proj) {
            $proj->delete();
            if ($this->activeProjectId === $projectId) {
                $this->activeProjectId = null;
            }
            unset($this->projects);
        }
    }

    // =========================================================================
    // KONFIGURATOR-EDITOR LOGIK (SEITEN, ELEMENTE, QR-CODE, SYMBOLE, UPLOAD)
    // =========================================================================

    public function switchSide(string $side): void
    {
        $this->assertStaffAccess();
        $target = ($side === 'back' && $this->projectHasBackSide) ? 'back' : 'front';
        if ($target === $this->activeSide) {
            return;
        }

        $this->saveMetadataOnly(silent: true);
        $this->activeSide = $target;
        $this->workflowStep = 1;
        $this->selectedTextIdx = null;
        $this->selectedLogoIdx = null;

        if ($this->activeProjectId) {
            $proj = LaserStudioProject::with('product')->find($this->activeProjectId);
            if ($proj) {
                $this->loadSideCoordinatesAndParams($proj, $target);
            }
        }
    }

    protected function loadSideCoordinatesAndParams(LaserStudioProject $project, string $side): void
    {
        $conf = is_array($project->configuration) ? $project->configuration : [];
        $coords = is_array($conf['laser_cockpit_coordinates'][$side] ?? null)
            ? $conf['laser_cockpit_coordinates'][$side]
            : [];

        $isFreeBed = ($project->mode === 'free_bed') || (((float) $project->width_mm) >= 198.0 && ((float) $project->height_mm) >= 198.0);
        if ($isFreeBed) {
            // Im freien 200x200 mm Kamera-Modus repräsentiert der 2D-Konfigurator 1:1 das gesamte 200x200 mm Bett!
            // Ein verschobener Koordinatenwert aus früheren Läufen (z. B. Y=135,3 mm) darf NIEMALS die Platzierung versetzen.
            $this->centerXMm = 100.0;
            $this->centerYMm = 100.0;
            $this->angleDeg = 0.0;
        } else {
            $this->centerXMm = round(max(5.0, min(195.0, (float) ($coords['center_x_mm'] ?? 100.0))), 2);
            $this->centerYMm = round(max(5.0, min(195.0, (float) ($coords['center_y_mm'] ?? 100.0))), 2);
            $this->angleDeg = round((float) ($coords['angle_deg'] ?? 0.0), 2);
        }
        $this->framingMode = (string) ($conf['framing_mode'] ?? 'contour');
        $this->cameraLightMode = XtoolLaserCockpitService::resolveDefaultCameraLightMode($project->product ?? null, $conf, $side);

        $effective = app(XtoolLaserCockpitService::class)->resolveEffectiveLaserParameters($project, $side);
        $this->laserParams = [
            'equipment_key' => (string) ($effective['equipment_key'] ?? 'flatbed_standard'),
            'process_mode' => (string) ($effective['process_mode'] ?? 'Prozess auf flacher Oberfläche'),
            'material' => (string) ($effective['material'] ?? 'Eloxiertes Aluminium'),
            'focus_mm' => (float) ($effective['focus_mm'] ?? 0.5),
            'element_type' => (string) ($effective['element_type'] ?? 'Bild'),
            'engrave_tab' => (string) ($effective['engrave_tab'] ?? 'Gravieren'),
            'preset_mode' => (string) ($effective['preset_mode'] ?? 'Benutzerdefiniert'),
            'power_percent' => (int) ($effective['power_percent'] ?? 65),
            'speed_mms' => (int) ($effective['speed_mms'] ?? 350),
            'dpi' => (int) ($effective['dpi'] ?? 500),
            'dot_duration_us' => (int) ($effective['dot_duration_us'] ?? 90),
            'frequency_khz' => (int) ($effective['frequency_khz'] ?? 60),
            'passes' => (int) ($effective['passes'] ?? 1),
            'bitmap_mode' => (string) ($effective['bitmap_mode'] ?? 'Schwellenwert'),
            'engrave_mode' => (string) ($effective['engrave_mode'] ?? 'Bidirektional'),
            'vector_note' => (string) ($effective['vector_note'] ?? ''),
        ];
    }

    public function addTextLayer(?string $initialText = null): void
    {
        $this->assertStaffAccess();
        $list = $this->activeSide === 'back' ? $this->textsBack : $this->texts;
        $yDefault = min(85, 35 + (count($list) * 16));
        $newLayer = [
            'id' => 'txt_' . Str::lower(Str::random(6)),
            'text' => $initialText !== null && trim($initialText) !== '' ? trim($initialText) : 'Mein Seelenfunke',
            'font' => 'Cinzel',
            'size' => 1.0,
            'x' => 50.0,
            'y' => (float) $yDefault,
            'align' => 'center',
            'rotation' => 0,
        ];

        if ($this->activeSide === 'back') {
            $this->textsBack[] = $newLayer;
            $this->selectedTextIdx = count($this->textsBack) - 1;
        } else {
            $this->texts[] = $newLayer;
            $this->selectedTextIdx = count($this->texts) - 1;
        }
        $this->selectedLogoIdx = null;
        $this->saveProject(silent: true);
    }

    public function removeTextLayer(int $idx): void
    {
        $this->assertStaffAccess();
        if ($this->activeSide === 'back') {
            unset($this->textsBack[$idx]);
            $this->textsBack = array_values($this->textsBack);
        } else {
            unset($this->texts[$idx]);
            $this->texts = array_values($this->texts);
        }
        $this->selectedTextIdx = null;
        $this->saveProject(silent: true);
    }

    public function addQrCodeToCanvas(?string $presetKey = null): void
    {
        $this->assertStaffAccess();
        $url = trim($this->qrTargetUrl);
        $subtitle = trim($this->qrSubtitle);

        if ($presetKey !== null) {
            $templates = LaserQrCodeService::getQuickLinkTemplates();
            if (isset($templates[$presetKey])) {
                $url = $templates[$presetKey]['url'];
                $subtitle = $templates[$presetKey]['subtitle'];
                $this->qrTargetUrl = $url;
                $this->qrSubtitle = $subtitle;
            }
        }

        if ($url === '') {
            return;
        }

        $qr = LaserQrCodeService::generateLaserReadyQrSvg($url);
        $logoEntry = [
            'id' => 'qr_' . Str::lower(Str::random(6)),
            'name' => 'QR: ' . Str::limit($url, 32),
            'url' => $qr['url'],
            'x' => 50.0,
            'y' => $subtitle !== '' ? 44.0 : 50.0,
            'size' => 55.0,
            'rotation' => 0,
            'is_qr' => true,
            'qr_target' => $url,
        ];

        if ($this->activeSide === 'back') {
            $this->logosBack[] = $logoEntry;
            $this->selectedLogoIdx = count($this->logosBack) - 1;
        } else {
            $this->logos[] = $logoEntry;
            $this->selectedLogoIdx = count($this->logos) - 1;
        }

        if ($subtitle !== '') {
            $subLayer = [
                'id' => 'txt_' . Str::lower(Str::random(6)),
                'text' => $subtitle,
                'font' => 'Arial',
                'size' => 0.72,
                'x' => 50.0,
                'y' => 82.0,
                'align' => 'center',
                'rotation' => 0,
            ];
            if ($this->activeSide === 'back') {
                $this->textsBack[] = $subLayer;
            } else {
                $this->texts[] = $subLayer;
            }
        }

        $this->selectedTextIdx = null;
        $this->saveProject(silent: true);
        $this->notifyStatus('Vektor-QR-Code wurde auf der Arbeitsfläche platziert.');
    }

    public function addSymbolToCanvas(string $url, string $name = 'Symbol'): void
    {
        $this->assertStaffAccess();
        if (trim($url) === '') {
            return;
        }

        $entry = [
            'id' => 'sym_' . Str::lower(Str::random(6)),
            'name' => $name,
            'url' => $url,
            'x' => 50.0,
            'y' => 50.0,
            'size' => 42.0,
            'rotation' => 0,
        ];

        if ($this->activeSide === 'back') {
            $this->logosBack[] = $entry;
            $this->selectedLogoIdx = count($this->logosBack) - 1;
        } else {
            $this->logos[] = $entry;
            $this->selectedLogoIdx = count($this->logos) - 1;
        }
        $this->selectedTextIdx = null;
        $this->saveProject(silent: true);
    }

    public function updatedStudioUploadFile(): void
    {
        $this->assertStaffAccess();
        $this->validate([
            'studioUploadFile' => 'required|file|mimes:png,jpg,jpeg,webp,svg|max:15360',
        ]);

        $ext = strtolower((string) $this->studioUploadFile->getClientOriginalExtension());
        if ($ext === 'svg') {
            $rawSvg = (string) @file_get_contents($this->studioUploadFile->getRealPath());
            if (stripos($rawSvg, '<script') !== false || stripos($rawSvg, 'onload=') !== false) {
                $this->notifyStatus('Unsichere SVG-Datei abgelehnt.', 'error');
                $this->studioUploadFile = null;
                return;
            }
        }

        $safeName = now()->format('Ymd_His') . '_' . Str::lower(Str::random(6)) . '.' . $ext;
        $storedRel = $this->studioUploadFile->storeAs('laser-studio/uploads', $safeName, 'public');
        $origLabel = pathinfo((string) $this->studioUploadFile->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Upload';

        $this->addSymbolToCanvas('/storage/' . $storedRel, mb_substr($origLabel, 0, 40));
        $this->studioUploadFile = null;
        $this->notifyStatus('Grafik wurde auf der Arbeitsfläche eingefügt.');
    }

    public function removeLogoLayer(int $idx): void
    {
        $this->assertStaffAccess();
        if ($this->activeSide === 'back') {
            unset($this->logosBack[$idx]);
            $this->logosBack = array_values($this->logosBack);
        } else {
            unset($this->logos[$idx]);
            $this->logos = array_values($this->logos);
        }
        $this->selectedLogoIdx = null;
        $this->saveProject(silent: true);
    }

    public function updateElementPosition(string $kind, int $idx, float $xPct, float $yPct): void
    {
        $this->assertStaffAccess();
        $clampedX = round(max(2.0, min(98.0, $xPct)), 2);
        $clampedY = round(max(2.0, min(98.0, $yPct)), 2);

        if ($kind === 'text') {
            if ($this->activeSide === 'back' && isset($this->textsBack[$idx])) {
                $this->textsBack[$idx]['x'] = $clampedX;
                $this->textsBack[$idx]['y'] = $clampedY;
                $this->selectedTextIdx = $idx;
                $this->selectedLogoIdx = null;
            } elseif ($this->activeSide === 'front' && isset($this->texts[$idx])) {
                $this->texts[$idx]['x'] = $clampedX;
                $this->texts[$idx]['y'] = $clampedY;
                $this->selectedTextIdx = $idx;
                $this->selectedLogoIdx = null;
            }
        } elseif ($kind === 'logo') {
            if ($this->activeSide === 'back' && isset($this->logosBack[$idx])) {
                $this->logosBack[$idx]['x'] = $clampedX;
                $this->logosBack[$idx]['y'] = $clampedY;
                $this->selectedLogoIdx = $idx;
                $this->selectedTextIdx = null;
            } elseif ($this->activeSide === 'front' && isset($this->logos[$idx])) {
                $this->logos[$idx]['x'] = $clampedX;
                $this->logos[$idx]['y'] = $clampedY;
                $this->selectedLogoIdx = $idx;
                $this->selectedTextIdx = null;
            }
        }

        $this->saveProject(silent: true);
    }

    public function updateBedFramePosition(float $xMm, float $yMm, ?float $angle = null): void
    {
        $this->assertStaffAccess();
        $this->centerXMm = round(max(5.0, min(195.0, $xMm)), 2);
        $this->centerYMm = round(max(5.0, min(195.0, $yMm)), 2);
        if ($angle !== null) {
            $this->angleDeg = round(max(-180.0, min(180.0, $angle)), 2);
        }
        $this->saveProject(silent: true);
    }

    public function saveProject(bool $silent = false, bool $preserveCanvasFromDb = false): ?LaserStudioProject
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId) {
            return null;
        }

        $project = LaserStudioProject::with('product')->find($this->activeProjectId);
        if (!$project) {
            return null;
        }

        $project->title = mb_substr(trim($this->projectTitle) ?: 'Laser-Projekt', 0, 180);
        $project->width_mm = round(max(5.0, min(200.0, (float) $this->projectWidthMm)), 2);
        $project->height_mm = round(max(5.0, min(200.0, (float) $this->projectHeightMm)), 2);
        $project->contour_shape = in_array($this->projectContourShape, ['rounded_card', 'rect', 'circle'], true)
            ? $this->projectContourShape
            : 'rect';
        $project->has_back_side = (bool) $this->projectHasBackSide;
        $project->surface_tone = in_array($this->surfaceTone, ['dark_card', 'gold_card', 'light_surface'], true)
            ? $this->surfaceTone
            : 'dark_card';

        $conf = is_array($project->configuration) ? $project->configuration : [];
        if ($preserveCanvasFromDb) {
            $this->texts = array_values(is_array($conf['texts'] ?? null) ? $conf['texts'] : []);
            $this->logos = array_values(is_array($conf['logos'] ?? null) ? $conf['logos'] : []);
            $this->textsBack = array_values(is_array($conf['texts_back'] ?? null) ? $conf['texts_back'] : []);
            $this->logosBack = array_values(is_array($conf['logos_back'] ?? null) ? $conf['logos_back'] : []);
        } else {
            $conf['texts'] = array_values($this->texts);
            $conf['logos'] = array_values($this->logos);
            $conf['texts_back'] = array_values($this->textsBack);
            $conf['logos_back'] = array_values($this->logosBack);
        }
        $conf['framing_mode'] = in_array($this->framingMode, ['contour', 'rect'], true) ? $this->framingMode : 'contour';
        $conf['laser_camera_light_mode'] = XtoolLaserCockpitService::normalizeCameraLightMode($this->cameraLightMode);

        if (!isset($conf['laser_cockpit_coordinates']) || !is_array($conf['laser_cockpit_coordinates'])) {
            $conf['laser_cockpit_coordinates'] = [];
        }
        $isFreeBed = ($project->mode === 'free_bed') || (((float) $project->width_mm) >= 198.0 && ((float) $project->height_mm) >= 198.0);
        if ($isFreeBed) {
            $this->centerXMm = 100.0;
            $this->centerYMm = 100.0;
            $this->angleDeg = 0.0;
        }
        $conf['laser_cockpit_coordinates'][$this->activeSide] = [
            'center_x_mm' => $isFreeBed ? 100.0 : round(max(5.0, min(195.0, (float) $this->centerXMm)), 2),
            'center_y_mm' => $isFreeBed ? 100.0 : round(max(5.0, min(195.0, (float) $this->centerYMm)), 2),
            'angle_deg' => $isFreeBed ? 0.0 : round((float) $this->angleDeg, 2),
            'width_mm' => (float) $project->width_mm,
            'height_mm' => (float) $project->height_mm,
        ];

        if (!$silent) {
            $conf['has_unsaved_freehand_changes'] = false;
            $this->isProjectSaved = true;
        }

        $project->configuration = $conf;
        $project->syncStudioDimensionsToConfig();
        $project->save();

        app(XtoolLaserCockpitService::class)->saveOrderItemLaserParameterOverrides(
            $project,
            $this->activeSide,
            $this->laserParams,
            false,
            $this->selectedStudioPresetId
        );

        $this->dispatchFreehandStageGeometryUpdate($project);

        if (!$silent) {
            $this->dispatch('laser-studio-top-saved');
        }

        return $project->fresh('product');
    }

    /**
     * Speichert ausschließlich Kopf-/Cockpit-Metadaten (Titel, Maße, Laserwerte, Koordinaten),
     * ohne den live im eingebetteten `ProductConfigurator` bearbeiteten Canvas-Stand zu überschreiben.
     */
    public function saveMetadataOnly(bool $silent = true): ?LaserStudioProject
    {
        return $this->saveProject(silent: $silent, preserveCanvasFromDb: true);
    }

    /**
     * Empfängt das Speicher-Event aus dem eingebetteten echten `ProductConfigurator` (`context = 'laser_freehand'`).
     * Bewahrt `raw_files`, `files` und `laser_photo_adjustments` und erzeugt bewusst KEINEN Banner-Hinweis oben,
     * da der "Projekt speichern"-Button selbst inline grün ("Erfolgreich gespeichert!") wird.
     */
    #[On('save-laser-freehand-data')]
    public function handleFreehandConfiguratorSaved(?string $projectId = null, array $configData = [], string $activeSide = 'front'): void
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId || ($projectId !== null && $projectId !== '' && $projectId !== $this->activeProjectId)) {
            return;
        }

        if (isset($configData['texts']) && is_array($configData['texts'])) {
            $this->texts = array_values($configData['texts']);
        }
        if (isset($configData['logos']) && is_array($configData['logos'])) {
            $this->logos = array_values($configData['logos']);
        }
        if (isset($configData['texts_back']) && is_array($configData['texts_back'])) {
            $this->textsBack = array_values($configData['texts_back']);
        }
        if (isset($configData['logos_back']) && is_array($configData['logos_back'])) {
            $this->logosBack = array_values($configData['logos_back']);
        }
        if (in_array($activeSide, ['front', 'back'], true)) {
            $this->activeSide = $activeSide;
        }

        $this->isProjectSaved = true;
        $this->saveProject(silent: true, preserveCanvasFromDb: false);
        if ($this->activeProjectId) {
            $fresh = LaserStudioProject::find($this->activeProjectId);
            if ($fresh && is_array($fresh->configuration)) {
                $c = $fresh->configuration;
                $c['has_unsaved_freehand_changes'] = false;
                $fresh->configuration = $c;
                $fresh->save();
            }
        }
        $this->uploadedFilesRevision++;
    }

    #[On('laser-freehand-project-dirty')]
    public function handleFreehandProjectDirty(?string $projectId = null): void
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId || ($projectId !== null && $projectId !== '' && $projectId !== $this->activeProjectId)) {
            return;
        }
        $this->isProjectSaved = false;
    }

    #[On('laser-freehand-files-updated')]
    public function handleFreehandFilesUpdated(?string $projectId = null): void
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId || ($projectId !== null && $projectId !== '' && $projectId !== $this->activeProjectId)) {
            return;
        }

        $project = LaserStudioProject::find($this->activeProjectId);
        if ($project && is_array($project->configuration)) {
            $conf = $project->configuration;
            $this->texts = array_values(is_array($conf['texts'] ?? null) ? $conf['texts'] : []);
            $this->logos = array_values(is_array($conf['logos'] ?? null) ? $conf['logos'] : []);
            $this->textsBack = array_values(is_array($conf['texts_back'] ?? null) ? $conf['texts_back'] : []);
            $this->logosBack = array_values(is_array($conf['logos_back'] ?? null) ? $conf['logos_back'] : []);
        }

        $this->uploadedFilesRevision++;
    }

    protected function dispatchFreehandStageGeometryUpdate(LaserStudioProject $project): void
    {
        $virtualCfg = $project->toVirtualConfiguratorProduct($this->activeSide)->configurator_settings ?? [];
        $this->dispatch('laser-camera-updated',
            cameraUrl: $this->cameraUrl !== '' ? $this->cameraUrl : \App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::buildLaserBedGridSvgDataUrl(),
            hasBackSide: (bool) $project->has_back_side,
            areaBox: [
                'left' => (float) ($virtualCfg['area_left'] ?? 0.0),
                'top' => (float) ($virtualCfg['area_top'] ?? 0.0),
                'width' => (float) ($virtualCfg['area_width'] ?? 100.0),
                'height' => (float) ($virtualCfg['area_height'] ?? 100.0),
                'shape' => (string) ($virtualCfg['area_shape'] ?? 'rect'),
            ]
        );
    }

    /**
     * Berechnet im Free-Hand-Modus (`free_bed`, 200x200 mm) den millimetergenauen engen Lichtrahmen
     * exakt um die platzierten Gravur-Elemente (z. B. direkt um den Schriftzug auf der Scherenklinge!)
     * statt den äußeren 200x200 mm Bettrahmen abzufahren.
     *
     * @return ?array{center_x_mm: float, center_y_mm: float, width_mm: float, height_mm: float}
     */
    public function computeRenderedContentBoundingBoxMm(LaserStudioProject $project, string $side = 'front'): ?array
    {
        $payload = app(FileDownloadService::class)->buildLaserDesignPayload($project, $side, true);
        $pngBytes = (string) ($payload['stageEngravePngBytes'] ?? '');
        if ($pngBytes === '') {
            return null;
        }

        $img = @imagecreatefromstring($pngBytes);
        if (!$img) {
            return null;
        }

        $wPx = imagesx($img);
        $hPx = imagesy($img);
        if ($wPx <= 0 || $hPx <= 0) {
            imagedestroy($img);
            return null;
        }

        $minX = $wPx;
        $maxX = -1;
        $minY = $hPx;
        $maxY = -1;

        for ($y = 0; $y < $hPx; $y++) {
            for ($x = 0; $x < $wPx; $x++) {
                $c = imagecolorat($img, $x, $y);
                $alpha = ($c >> 24) & 0x7F;
                $gray = ($c >> 16) & 0xFF;
                if ($alpha < 120 && $gray < 245) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }
        imagedestroy($img);

        if ($maxX < $minX || $maxY < $minY) {
            return null;
        }

        $stageWMm = max(1.0, (float) ($payload['widthMm'] ?? 200.0));
        $stageHMm = max(1.0, (float) ($payload['heightMm'] ?? 200.0));

        $leftMm = ($minX / $wPx) * $stageWMm;
        $rightMm = (($maxX + 1) / $wPx) * $stageWMm;
        $topMm = ($minY / $hPx) * $stageHMm;
        $bottomMm = (($maxY + 1) / $hPx) * $stageHMm;

        $contentWMm = round(max(2.0, min($stageWMm, ($rightMm - $leftMm) + 1.0)), 2);
        $contentHMm = round(max(2.0, min($stageHMm, ($bottomMm - $topMm) + 1.0)), 2);
        $localCenterX = ($leftMm + $rightMm) * 0.5;
        $localCenterY = ($topMm + $bottomMm) * 0.5;

        $bedCenterX = round(max(2.0, min(198.0, $this->centerXMm + ($localCenterX - ($stageWMm * 0.5)))), 2);
        $bedCenterY = round(max(2.0, min(198.0, $this->centerYMm + ($localCenterY - ($stageHMm * 0.5)))), 2);

        return [
            'center_x_mm' => $bedCenterX,
            'center_y_mm' => $bedCenterY,
            'width_mm' => $contentWMm,
            'height_mm' => $contentHMm,
        ];
    }

    // =========================================================================
    // STUDIO-MATERIAL-PRESETS & LASER-COCKPIT AKTIONEN (SCHRITT 1 & 2)
    // =========================================================================

    public function applyStudioPreset(string $presetId): void
    {
        $this->assertStaffAccess();
        $this->selectedStudioPresetId = $presetId;

        foreach ($this->studioPresets as $preset) {
            if (($preset['id'] ?? '') === $presetId && is_array($preset['params'] ?? null)) {
                $this->laserParams = array_merge($this->laserParams, $preset['params']);
                if (!empty($preset['surface_tone'])) {
                    $this->surfaceTone = (string) $preset['surface_tone'];
                }
                $this->saveMetadataOnly(silent: true);
                $this->notifyStatus("Material-Preset '{$preset['name']}' geladen.");
                return;
            }
        }
    }

    public function saveCurrentAsStudioPreset(): void
    {
        $this->assertStaffAccess();
        $name = trim($this->newCustomPresetName);
        if ($name === '') {
            $this->notifyStatus('Bitte kurzen Namen für das Studio-Preset eingeben.', 'warning');
            return;
        }

        $saved = LaserQrCodeService::saveCustomStudioPreset($name, $this->laserParams, $this->surfaceTone);
        $this->selectedStudioPresetId = (string) $saved['id'];
        $this->newCustomPresetName = '';
        unset($this->studioPresets);
        $this->notifyStatus("Eigenes Studio-Preset '{$saved['name']}' gespeichert.");
    }

    public function deleteSelectedCustomPreset(): void
    {
        $this->assertStaffAccess();
        if (!str_starts_with($this->selectedStudioPresetId, 'custom_')) {
            return;
        }
        LaserQrCodeService::deleteCustomStudioPreset($this->selectedStudioPresetId);
        $this->selectedStudioPresetId = 'builtin_card_black_alu';
        unset($this->studioPresets);
        $this->notifyStatus('Eigenes Studio-Preset gelöscht.');
    }

    protected function hydrateCachedCameraState(): void
    {
        $publicDisk = Storage::disk('public');
        $rel = 'bestellungen/xtool/background.jpg';
        if ($publicDisk->exists($rel)) {
            $ts = @filemtime($publicDisk->path($rel)) ?: time();
            $this->cameraUrl = '/storage/' . $rel . '?v=' . $ts;
            $this->cameraUpdatedAt = date('H:i:s', $ts);
        }
    }

    public function refreshCameraOnly(): void
    {
        $this->assertStaffAccess();
        $project = $this->saveMetadataOnly(silent: true);

        $service = app(XtoolLaserCockpitService::class);
        $cam = $service->syncCameraFromHostOrLaser(
            (float) ($this->laserParams['focus_mm'] ?? 1.0),
            true,
            XtoolLaserCockpitService::DEFAULT_LASER_IP,
            $this->cameraLightMode
        );

        $this->cameraUrl = (string) ($cam['camera_url'] ?? $this->cameraUrl);
        $this->laserOnline = !empty($cam['laser_online']);
        $this->cameraUpdatedAt = now('Europe/Berlin')->format('H:i:s');
        if ($project) {
            $this->dispatchFreehandStageGeometryUpdate($project);
        }
        $this->notifyStatus(
            $this->laserOnline
                ? 'Live-Kamerabild vom xTool F2 Ultra UV geladen.'
                : 'Kamerabild aus lokalem Cache synchronisiert (Laser aktuell im Standby/Offline).'
        );
    }

    /**
     * Button 1: Kamera & KI-Ausrichten (exakt wie in Schritt 1 der Bestellung).
     */
    public function refreshLaserCameraAndAlign(): void
    {
        $this->assertStaffAccess();
        $project = $this->saveMetadataOnly(silent: true);
        if (!$project) {
            return;
        }

        $service = app(XtoolLaserCockpitService::class);
        $aligned = $service->detectBlankPositionOnBed(
            $project->product,
            is_array($project->configuration) ? $project->configuration : [],
            $this->activeSide,
            true,
            $this->cameraLightMode,
            (float) ($this->laserParams['focus_mm'] ?? 0.0) > 0.0 ? (float) $this->laserParams['focus_mm'] : null
        );

        $isFreeBed = ($project->mode === 'free_bed') || ($this->projectWidthMm >= 198.0 && $this->projectHeightMm >= 198.0);
        if ($isFreeBed) {
            // Im freien 200x200 mm Kamera-Modus bildet der Konfigurator 1:1 das gesamte 200x200 mm Bett ab (Mitte 100,100 mm).
            // Ein Verschieben von centerXMm/centerYMm auf den Schwerpunkt der Schere würde die auf dem Foto platzierten Elemente versetzen!
            $this->centerXMm = 100.0;
            $this->centerYMm = 100.0;
            $this->angleDeg = 0.0;
        } else {
            $this->centerXMm = round((float) ($aligned['center_x_mm'] ?? $this->centerXMm), 2);
            $this->centerYMm = round((float) ($aligned['center_y_mm'] ?? $this->centerYMm), 2);
            $this->angleDeg = round((float) ($aligned['angle_deg'] ?? $this->angleDeg), 2);
        }

        if (!empty($aligned['camera_url'])) {
            $this->cameraUrl = (string) $aligned['camera_url'];
        }
        $this->laserOnline = !empty($aligned['laser_online']);
        $this->cameraUpdatedAt = now('Europe/Berlin')->format('H:i:s');
        $this->workflowStep = 2;

        $this->saveMetadataOnly(silent: true);

        if ($isFreeBed) {
            $detHint = !empty($aligned['detected'])
                ? ' · Objekt erkannt bei X=' . round((float) ($aligned['center_x_mm'] ?? 100.0), 1) . ', Y=' . round((float) ($aligned['center_y_mm'] ?? 100.0), 1) . ' mm'
                : '';
            $this->notifyStatus("Schritt 1 fertig: 1:1 Live-Kamerabild geladen{$detHint} (bereit für Schritt 2).");
        } else {
            $msg = !empty($aligned['detected'])
                ? "Schritt 1 fertig: Werkstück erkannt & ausgerichtet (X={$this->centerXMm} mm, Y={$this->centerYMm} mm, {$this->angleDeg}°)."
                : "Schritt 1 fertig: Kamerabild geladen (Position X={$this->centerXMm} mm, Y={$this->centerYMm} mm bereit für Schritt 2).";
            $this->notifyStatus($msg);
        }
    }

    public function triggerFraming(): void
    {
        $this->assertStaffAccess();
        $project = $this->saveMetadataOnly(silent: true);
        if (!$project) {
            return;
        }

        $frameCenterX = $this->centerXMm;
        $frameCenterY = $this->centerYMm;
        $frameWidth = $this->projectWidthMm;
        $frameHeight = $this->projectHeightMm;
        $effectiveFramingMode = $this->framingMode;

        $isFreeBed = ($project->mode === 'free_bed') || ($this->projectWidthMm >= 198.0 && $this->projectHeightMm >= 198.0);
        if ($isFreeBed) {
            $tightBounds = $this->computeRenderedContentBoundingBoxMm($project, $this->activeSide);
            if ($tightBounds !== null) {
                $frameCenterX = $tightBounds['center_x_mm'];
                $frameCenterY = $tightBounds['center_y_mm'];
                $frameWidth = $tightBounds['width_mm'];
                $frameHeight = $tightBounds['height_mm'];
                $effectiveFramingMode = 'rect';
            }
        }

        $res = app(XtoolLaserCockpitService::class)->enqueueOrSendLaserJob(
            $project,
            $this->activeSide,
            'framing',
            $frameCenterX,
            $frameCenterY,
            $this->angleDeg,
            XtoolLaserCockpitService::DEFAULT_LASER_IP,
            $frameWidth,
            $frameHeight,
            $effectiveFramingMode
        );

        $sentDirect = !empty($res['sent_direct']) || !empty($res['direct_sent']);
        $this->notifyStatus(
            $sentDirect
                ? "Vorschaurahmen ({$frameWidth}×{$frameHeight} mm bei X={$frameCenterX}, Y={$frameCenterY} mm) leuchtet live am xTool F2 Ultra UV."
                : "Vorschaurahmen ({$frameWidth}×{$frameHeight} mm bei X={$frameCenterX}, Y={$frameCenterY} mm) erzeugt und in die Laser-Warteschlange gelegt."
        );
    }

    /**
     * Button 2: An Laser senden (exakt wie in Schritt 1 der Bestellung).
     * Sicherheitssperre: Erst aktiv, wenn das Projekt im Konfigurator gespeichert wurde ($isProjectSaved === true)!
     */
    public function sendProjectDirectlyToLaser(): void
    {
        $this->assertStaffAccess();
        if (!$this->isProjectSaved) {
            $this->notifyStatus('Bitte zuerst unten im Konfigurator auf „Projekt speichern“ klicken, bevor du an den Laser sendest.', 'warning');
            return;
        }

        $project = $this->saveMetadataOnly(silent: true);
        if (!$project) {
            return;
        }

        $sideTexts = $this->activeSide === 'back' ? $this->textsBack : $this->texts;
        $sideLogos = $this->activeSide === 'back' ? $this->logosBack : $this->logos;
        if (empty($sideTexts) && empty($sideLogos)) {
            $this->notifyStatus('Bitte zuerst mindestens einen Text, QR-Code, ein Symbol oder Bild auf dieser Seite platzieren.', 'warning');
            return;
        }

        $isFreeBed = ($project->mode === 'free_bed') || ($this->projectWidthMm >= 198.0 && $this->projectHeightMm >= 198.0);
        $engraveCenterX = $isFreeBed ? 100.0 : $this->centerXMm;
        $engraveCenterY = $isFreeBed ? 100.0 : $this->centerYMm;
        $engraveAngle = $isFreeBed ? 0.0 : $this->angleDeg;

        $res = app(XtoolLaserCockpitService::class)->enqueueOrSendLaserJob(
            $project,
            $this->activeSide,
            'engrave',
            $engraveCenterX,
            $engraveCenterY,
            $engraveAngle,
            XtoolLaserCockpitService::DEFAULT_LASER_IP,
            $this->projectWidthMm,
            $this->projectHeightMm,
            $this->framingMode
        );

        $project->update(['last_lasered_at' => now()]);
        $this->workflowStep = 3;

        $sentDirect = !empty($res['sent_direct']) || !empty($res['direct_sent']);
        $this->notifyStatus(
            $sentDirect
                ? 'Schritt 2 erfolgreich: Laserprojekt direkt an den xTool F2 Ultra UV übertragen! Bitte Startknopf am Gerät drücken.'
                : 'Schritt 2 bereit: .xcs & .xf Laserdateien erzeugt und in die xTool-AutoStart-Warteschlange übergeben.'
        );
    }

    public function measureAutoFocus(): void
    {
        $this->assertStaffAccess();
        $res = app(XtoolLaserCockpitService::class)->autoMeasureFocus();
        if (!empty($res['ok']) && isset($res['focus_mm']) && is_numeric($res['focus_mm'])) {
            $this->laserParams['focus_mm'] = round((float) $res['focus_mm'], 2);
            $project = $this->saveMetadataOnly(silent: true);

            // Wie in xTool Studio (setBackGroundImg nach autoFocusFinished): Kamera sofort auf der neuen Fokus-Höhe aktualisieren,
            // damit kein Parallaxe-Versatz durch ein älteres Kamerabild auf anderer Z-Höhe entsteht!
            $cam = app(XtoolLaserCockpitService::class)->syncCameraFromHostOrLaser(
                (float) $this->laserParams['focus_mm'],
                true,
                XtoolLaserCockpitService::DEFAULT_LASER_IP,
                $this->cameraLightMode
            );
            if (!empty($cam['camera_url'])) {
                $this->cameraUrl = (string) $cam['camera_url'];
                $this->cameraUpdatedAt = now('Europe/Berlin')->format('H:i:s');
            }
            if ($project) {
                $this->dispatchFreehandStageGeometryUpdate($project);
            }

            $this->notifyStatus("Auto-Fokus gemessen ({$this->laserParams['focus_mm']} mm) & Live-Kamerabild aktualisiert.");
        } else {
            $this->notifyStatus((string) ($res['error'] ?? 'Auto-Fokus aktuell nicht erreichbar (bitte Fokus manuell eintragen).'), 'warning');
        }
    }

    public function stopLaser(): void
    {
        $this->assertStaffAccess();
        app(XtoolLaserCockpitService::class)->stopLaserProcess();
        $this->notifyStatus('STOP-Befehl an den xTool F2 Ultra UV gesendet.', 'warning');
    }

    public function downloadXcsProject()
    {
        $this->assertStaffAccess();
        $project = $this->saveMetadataOnly(silent: true);
        if (!$project) {
            return null;
        }

        $isFreeBed = ($project->mode === 'free_bed') || ($this->projectWidthMm >= 198.0 && $this->projectHeightMm >= 198.0);
        $xcs = app(XtoolLaserCockpitService::class)->generateXcsProjectBytes(
            $project,
            $this->activeSide,
            $isFreeBed ? 100.0 : $this->centerXMm,
            $isFreeBed ? 100.0 : $this->centerYMm,
            $isFreeBed ? 0.0 : $this->angleDeg,
            $this->projectWidthMm,
            $this->projectHeightMm
        );

        return response()->streamDownload(function () use ($xcs) {
            echo $xcs['bytes'];
        }, $xcs['filename'], [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    // =========================================================================
    // DATEIUPLOAD-VERWALTUNG & 60-FPS LASER-FOTO-STUDIO (ANALOG BESTELLUNG SCHRITT 1)
    // =========================================================================

    #[Computed]
    public function projectUploadedFiles(): array
    {
        if (!$this->activeProjectId || !Schema::hasTable('laser_studio_projects')) {
            return [];
        }

        // Bind an uploadedFilesRevision, damit Livewire den Computed-Cache nach Upload/Bearbeitung aktualisiert
        $_rev = $this->uploadedFilesRevision;

        $project = LaserStudioProject::find($this->activeProjectId);
        if (!$project || !is_array($project->configuration)) {
            return [];
        }

        $conf = $project->configuration;
        $rawCandidates = [];

        // Nur Dateien listen, die aktuell auch wirklich auf Vorder- oder Rückseite im Projekt platziert sind
        // (bzw. echte PDF-Anhänge in files[]), damit ein vom Canvas gelöschter alter QR-Code sofort verschwindet!
        foreach (['logos', 'logos_back'] as $logoListKey) {
            foreach (($conf[$logoListKey] ?? []) as $logoEntry) {
                if (!is_array($logoEntry)) {
                    continue;
                }
                $lType = strtolower((string) ($logoEntry['type'] ?? ''));
                if ($lType === 'vector') {
                    continue;
                }
                $candidateVal = (string) ($logoEntry['value'] ?? $logoEntry['url'] ?? '');
                if (
                    $candidateVal === ''
                    || str_starts_with($candidateVal, 'data:')
                    || str_starts_with($candidateVal, 'vectors/')
                    || str_contains($candidateVal, 'produkte/configurator/vectors/')
                ) {
                    continue;
                }
                $cPath = $this->normalizeStudioRelPath($candidateVal);
                if ($cPath !== '' && !isset($rawCandidates[$cPath])) {
                    $rawCandidates[$cPath] = [
                        'raw_key' => $cPath,
                        'label' => basename($cPath),
                        'is_qr' => !empty($logoEntry['is_qr']) || str_starts_with($cPath, 'laser-studio/qr/'),
                    ];
                }
            }
        }

        foreach (($conf['files'] ?? []) as $mf) {
            if (!is_string($mf)) {
                continue;
            }
            $cPath = $this->normalizeStudioRelPath($mf);
            if ($cPath !== '' && str_ends_with(strtolower($cPath), '.pdf') && !isset($rawCandidates[$cPath])) {
                $rawCandidates[$cPath] = [
                    'raw_key' => $cPath,
                    'label' => basename($cPath),
                    'is_qr' => false,
                ];
            }
        }

        // Falls sowohl eine Arbeitsdatei als auch deren *_raw.* Schwesterdatei in files[] stehen, _raw-Duplikat ausblenden
        $allKeys = array_keys($rawCandidates);
        foreach ($allKeys as $k) {
            if (preg_match('/^(.*)_raw(\.[a-z0-9]+)$/i', $k, $m)) {
                $basePrefix = $m[1];
                foreach ($allKeys as $other) {
                    if ($other !== $k && str_starts_with($other, $basePrefix . '.')) {
                        unset($rawCandidates[$k]);
                        break;
                    }
                }
            }
        }

        $adjustments = is_array($conf['laser_photo_adjustments'] ?? null) ? $conf['laser_photo_adjustments'] : [];
        $publicDisk = Storage::disk('public');
        $files = [];
        $idxCounter = 1;

        foreach ($rawCandidates as $rawKey => $entry) {
            $ext = strtolower(pathinfo($rawKey, PATHINFO_EXTENSION));
            $isRaster = in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true);
            $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true);

            $origRel = $isRaster ? LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($rawKey, $conf) : $rawKey;
            $origRel = $this->normalizeStudioRelPath($origRel) ?: $rawKey;
            $hasUntouched = $isRaster && LaserPhotoPreprocessor::hasUntouchedOriginal($rawKey, $conf);

            $adj = is_array($adjustments[$rawKey] ?? null) ? $adjustments[$rawKey] : null;
            $isLaserOptimized = !empty($adj['enabled']) && !empty($adj['edited_path']);
            $editedRel = $isLaserOptimized ? $this->normalizeStudioRelPath((string) $adj['edited_path']) : '';
            if ($editedRel !== '' && !$publicDisk->exists($editedRel)) {
                $editedRel = '';
                $isLaserOptimized = false;
            }

            $previewRel = $editedRel !== '' ? $editedRel : ($publicDisk->exists($origRel) ? $origRel : $rawKey);
            $previewAbs = $this->resolveStudioStorageAbsPath($previewRel);
            $ver = ($previewAbs !== null && @is_file($previewAbs)) ? ('?v=' . (@filemtime($previewAbs) ?: 1)) : '';

            $displayLabel = (!empty($entry['is_qr']) || str_starts_with($rawKey, 'bestellungen/xtool/qr-') || str_starts_with($rawKey, 'laser-studio/qr/'))
                ? ('QR-Code ' . $idxCounter . ' (SVG)')
                : ('Bild/Motiv ' . $idxCounter . ' (' . strtoupper($ext ?: 'FILE') . ')');
            $idxCounter++;

            $files[] = [
                'raw_key' => $rawKey,
                'label' => $displayLabel,
                'file_name' => basename($origRel) ?: $entry['label'],
                'ext' => $ext ?: 'file',
                'is_raster' => $isRaster,
                'is_image' => $isImage,
                'original_rel_path' => $origRel,
                'has_untouched_original' => $hasUntouched,
                'is_laser_optimized' => $isLaserOptimized,
                'edited_rel_path' => $editedRel,
                'preview_url' => asset('storage/' . $previewRel) . $ver,
                'original_preview_url' => asset('storage/' . $origRel),
                'download_url' => asset('storage/' . ($publicDisk->exists($origRel) ? $origRel : $rawKey)),
                'download_name' => basename($origRel) ?: $entry['label'],
            ];
        }

        return $files;
    }

    public function removeStudioUploadedFile(string $rawKey): void
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId) {
            return;
        }
        $cleanKey = $this->normalizeStudioRelPath($rawKey);
        if ($cleanKey === '') {
            return;
        }

        $project = LaserStudioProject::find($this->activeProjectId);
        if (!$project || !is_array($project->configuration)) {
            return;
        }

        $conf = $project->configuration;
        $conf['logos'] = array_values(array_filter(
            is_array($conf['logos'] ?? null) ? $conf['logos'] : [],
            fn ($l) => is_array($l) && $this->normalizeStudioRelPath((string) ($l['value'] ?? $l['url'] ?? '')) !== $cleanKey
        ));
        $conf['logos_back'] = array_values(array_filter(
            is_array($conf['logos_back'] ?? null) ? $conf['logos_back'] : [],
            fn ($l) => is_array($l) && $this->normalizeStudioRelPath((string) ($l['value'] ?? $l['url'] ?? '')) !== $cleanKey
        ));
        $conf['files'] = array_values(array_filter(
            is_array($conf['files'] ?? null) ? $conf['files'] : [],
            fn ($f) => is_string($f) && $this->normalizeStudioRelPath($f) !== $cleanKey
        ));
        if (isset($conf['laser_photo_adjustments'][$cleanKey])) {
            unset($conf['laser_photo_adjustments'][$cleanKey]);
        }

        $this->logos = $conf['logos'];
        $this->logosBack = $conf['logos_back'];
        $project->configuration = $conf;
        $project->save();

        $this->uploadedFilesRevision++;
        $this->dispatch('laser-freehand-remove-uploaded-file', projectId: $this->activeProjectId, fileKey: $cleanKey);
    }

    protected function normalizeStudioRelPath(string $path): string
    {
        $clean = trim(str_replace('\\', '/', $path));
        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, "\0")) {
            return '';
        }
        $clean = (string) preg_replace('#^https?://[^/]+/#i', '', $clean);
        $clean = ltrim($clean, '/');
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }
        $qPos = strpos($clean, '?');
        if ($qPos !== false) {
            $clean = substr($clean, 0, $qPos);
        }

        return trim($clean, '/');
    }

    protected function resolveStudioStorageAbsPath(?string $relPath): ?string
    {
        $clean = $this->normalizeStudioRelPath((string) $relPath);
        if ($clean === '') {
            return null;
        }
        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($clean)) {
            $abs = $publicDisk->path($clean);
            if (@is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }

    protected function sanitizePhotoStudioParams(array $raw): array
    {
        return LaserPhotoPreprocessor::sanitizeStudioParams($raw);
    }

    protected function mapBitmapModeToStudioDitherMode(?string $bitmapMode, string $fallback = 'jarvis'): string
    {
        $b = mb_strtolower(trim((string) $bitmapMode));
        if ($b === '') {
            return $fallback;
        }
        if (str_contains($b, 'stucki')) {
            return 'stucki';
        }
        if (str_contains($b, 'schwellenwert') || str_contains($b, 'threshold')) {
            return 'threshold';
        }
        if (str_contains($b, 'graustufen') || str_contains($b, 'greyscale') || str_contains($b, 'grayscale')) {
            return 'grayscale';
        }
        if (str_contains($b, 'jarvis') || str_contains($b, 'raster')) {
            return 'jarvis';
        }

        return $fallback;
    }

    protected function mapStudioDitherModeToBitmapMode(string $ditherMode): string
    {
        return match (strtolower(trim($ditherMode))) {
            'stucki' => 'Stucki',
            'threshold' => 'Schwellenwert',
            'grayscale' => 'Graustufen',
            default => 'Jarvis',
        };
    }

    protected function buildPhotoStudioProfilesList(): array
    {
        $out = [];
        foreach ($this->studioPresets as $idx => $sp) {
            $id = (string) ($sp['id'] ?? ('preset_' . $idx));
            $params = is_array($sp['params'] ?? null) ? $sp['params'] : [];
            $dpi = max(50, min(2540, (int) ($params['dpi'] ?? 600)));
            $bitmap = (string) ($params['bitmap_mode'] ?? 'Jarvis');
            $isDark = in_array(($sp['surface_tone'] ?? 'dark_card'), ['dark_card', 'gold_card'], true);
            $photoStudio = is_array($sp['photo_studio'] ?? null) ? $this->sanitizePhotoStudioParams($sp['photo_studio']) : array_merge(
                LaserPhotoPreprocessor::defaultStudioParams($isDark, $dpi),
                [
                    'enabled' => true,
                    'invert' => $isDark,
                    'dither_mode' => $this->mapBitmapModeToStudioDitherMode($bitmap, 'jarvis'),
                ]
            );

            $out[] = [
                'id' => $id,
                'title' => (string) ($sp['name'] ?? 'Standard'),
                'is_default' => $idx === 0,
                'dpi' => $dpi,
                'bitmap_mode' => $bitmap,
                'photo_studio' => $photoStudio,
            ];
        }

        return $out;
    }

    protected function studioRenderPreviewAssetUrl(string $sourceAbsPath, array $params, int $maxDim = 680): string
    {
        $sanitized = $this->sanitizePhotoStudioParams($params);
        $maxDim = max(120, min(1200, $maxDim));
        $hash = substr(sha1(
            $sourceAbsPath . '|'
            . (@filemtime($sourceAbsPath) ?: 0) . '|'
            . (@filesize($sourceAbsPath) ?: 0) . '|'
            . $maxDim . '|'
            . json_encode($sanitized)
        ), 0, 24);

        $relPreview = 'bestellungen/xtool/photo-edits/preview_' . $hash . '.png';
        $publicDisk = Storage::disk('public');

        if (!$publicDisk->exists($relPreview)) {
            $dataUrl = LaserPhotoPreprocessor::renderStudioPreviewDataUrl($sourceAbsPath, $sanitized, $maxDim);
            if (str_starts_with($dataUrl, 'data:image/png;base64,')) {
                $pngBytes = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
                if (is_string($pngBytes) && $pngBytes !== '') {
                    $publicDisk->put($relPreview, $pngBytes);
                }
            }
        }

        return $publicDisk->exists($relPreview) ? asset('storage/' . $relPreview) : '';
    }

    public function openLaserPhotoStudio(string $fileKey): void
    {
        $this->assertStaffAccess();
        if (!$this->activeProjectId) {
            return;
        }

        $project = LaserStudioProject::with('product')->find($this->activeProjectId);
        if (!$project) {
            return;
        }

        $cleanKey = $this->normalizeStudioRelPath($fileKey);
        $verifiedFile = collect($this->projectUploadedFiles)->first(
            fn (array $f): bool => $f['raw_key'] === $cleanKey && !empty($f['is_raster'])
        );
        if (!is_array($verifiedFile)) {
            $this->notifyStatus('Nur hochgeladene Rasterbilder (PNG/JPG/WEBP) können im Laser-Foto-Studio bearbeitet werden.', 'warning');
            return;
        }

        $conf = is_array($project->configuration) ? $project->configuration : [];
        $origRelPath = (string) ($verifiedFile['original_rel_path'] ?? $cleanKey);
        $sourceAbsPath = $this->resolveStudioStorageAbsPath($origRelPath) ?? $this->resolveStudioStorageAbsPath($cleanKey);
        if ($sourceAbsPath === null) {
            $this->notifyStatus('Die Original-Bilddatei konnte im Speicher nicht gefunden werden.', 'error');
            return;
        }

        $dpi = max(50, min(2540, (int) ($this->laserParams['dpi'] ?? 600)));
        $materialHint = (string) ($this->laserParams['material'] ?? '');
        $matLower = mb_strtolower($materialHint);
        $isDarkSurface = str_contains($matLower, 'elox')
            || str_contains($matLower, 'schwarz')
            || str_contains($matLower, 'glas')
            || str_contains($matLower, 'schiefer')
            || $this->surfaceTone === 'dark_card';

        $profiles = $this->buildPhotoStudioProfilesList();
        $activeProfileId = $this->selectedStudioPresetId !== '' ? $this->selectedStudioPresetId : (string) ($profiles[0]['id'] ?? '');

        $existingAdjust = is_array($conf['laser_photo_adjustments'][$cleanKey] ?? null)
            ? $conf['laser_photo_adjustments'][$cleanKey]
            : null;

        if ($existingAdjust !== null) {
            $initialParams = $this->sanitizePhotoStudioParams(
                array_merge(LaserPhotoPreprocessor::defaultStudioParams($isDarkSurface, $dpi), $existingAdjust, ['enabled' => true])
            );
        } else {
            $defaults = LaserPhotoPreprocessor::defaultStudioParams($isDarkSurface, $dpi);
            $defaults['dither_mode'] = $this->mapBitmapModeToStudioDitherMode((string) ($this->laserParams['bitmap_mode'] ?? 'Jarvis'), 'jarvis');
            $initialParams = $this->sanitizePhotoStudioParams(array_merge($defaults, ['enabled' => true]));
        }

        $ver = (int) (@filemtime($sourceAbsPath) ?: 1);
        $this->photoStudioItemId = (string) $project->id;
        $this->photoStudioFileKey = $cleanKey;
        $this->photoStudioFileName = basename($origRelPath) ?: basename($cleanKey);
        $this->photoStudioOriginalRelPath = $origRelPath;
        $this->photoStudioIsDarkSurface = $isDarkSurface;
        $this->photoStudioDpi = $dpi;
        $this->photoStudioMaterialHint = $materialHint;
        $this->photoStudioProfiles = $profiles;
        $this->photoStudioActiveProfileId = $activeProfileId;
        $this->photoStudioParams = $initialParams;
        $this->photoStudioOriginalUrl = asset('storage/' . $origRelPath) . '?v=' . $ver;
        $this->photoStudioPreviewUrl = $this->studioRenderPreviewAssetUrl($sourceAbsPath, $this->photoStudioParams, 680);
        $this->isLaserPhotoStudioOpen = true;

        $this->dispatch(
            'laser-studio-params-synced',
            params: $this->photoStudioParams,
            originalUrl: $this->photoStudioOriginalUrl,
            isDarkSurface: $this->photoStudioIsDarkSurface,
            activeProfileId: $this->photoStudioActiveProfileId,
            dpi: $this->photoStudioDpi
        );
    }

    public function closeLaserPhotoStudio(): void
    {
        $this->isLaserPhotoStudioOpen = false;
        $this->photoStudioItemId = null;
        $this->photoStudioFileKey = null;
        $this->photoStudioFileName = null;
        $this->photoStudioOriginalRelPath = null;
        $this->photoStudioOriginalUrl = null;
        $this->photoStudioPreviewUrl = null;
        $this->photoStudioProfiles = [];
        $this->photoStudioActiveProfileId = '';
    }

    public function autoOptimizeLaserPhoto(): void
    {
        $this->assertStaffAccess();
        if (!$this->isLaserPhotoStudioOpen || !$this->photoStudioOriginalRelPath) {
            return;
        }

        $sourceAbsPath = $this->resolveStudioStorageAbsPath($this->photoStudioOriginalRelPath)
            ?? ($this->photoStudioFileKey ? $this->resolveStudioStorageAbsPath($this->photoStudioFileKey) : null);
        if ($sourceAbsPath === null) {
            return;
        }

        $auto = LaserPhotoPreprocessor::computeMathematicalAutoParams(
            $sourceAbsPath,
            $this->photoStudioIsDarkSurface,
            $this->photoStudioDpi,
            $this->photoStudioMaterialHint
        );

        $this->photoStudioParams = $this->sanitizePhotoStudioParams(array_merge($this->photoStudioParams, $auto, ['enabled' => true]));
        $this->photoStudioPreviewUrl = $this->studioRenderPreviewAssetUrl($sourceAbsPath, $this->photoStudioParams, 680);
        $this->dispatch('laser-studio-params-synced', params: $this->photoStudioParams);
    }

    public function resetLaserPhotoStudioParams(): void
    {
        $this->assertStaffAccess();
        if (!$this->isLaserPhotoStudioOpen) {
            return;
        }

        $defaults = LaserPhotoPreprocessor::defaultStudioParams($this->photoStudioIsDarkSurface, $this->photoStudioDpi);
        $this->photoStudioParams = $this->sanitizePhotoStudioParams(array_merge($defaults, ['enabled' => true]));
        $this->dispatch('laser-studio-params-synced', params: $this->photoStudioParams);
    }

    public function selectPhotoStudioLaserProfile(string $profileId): void
    {
        $this->assertStaffAccess();
        if (!$this->isLaserPhotoStudioOpen) {
            return;
        }

        $cleanId = trim($profileId);
        $this->applyStudioPreset($cleanId);
        $this->statusNotice = null;
        $this->photoStudioActiveProfileId = $cleanId;
        $this->photoStudioDpi = max(50, min(2540, (int) ($this->laserParams['dpi'] ?? 600)));
        $this->photoStudioProfiles = $this->buildPhotoStudioProfilesList();

        $matched = collect($this->photoStudioProfiles)->firstWhere('id', $cleanId);
        if (is_array($matched) && is_array($matched['photo_studio'] ?? null)) {
            $this->photoStudioParams = $this->sanitizePhotoStudioParams(array_merge($matched['photo_studio'], ['enabled' => true]));
        }

        $this->dispatch(
            'laser-studio-params-synced',
            params: $this->photoStudioParams,
            activeProfileId: $this->photoStudioActiveProfileId,
            dpi: $this->photoStudioDpi
        );
    }

    public function savePhotoStudioToActiveLaserProfile(string $profileId, array $clientParams): void
    {
        $this->assertStaffAccess();
        $clean = $this->sanitizePhotoStudioParams(array_merge($clientParams, ['enabled' => true]));
        $this->photoStudioParams = $clean;
        $this->laserParams['bitmap_mode'] = $this->mapStudioDitherModeToBitmapMode((string) $clean['dither_mode']);
        $this->saveMetadataOnly(silent: true);
    }

    public function createLaserProfileFromPhotoStudio(string $newTitle, ?string $baseProfileId, array $clientParams): void
    {
        $this->assertStaffAccess();
        $cleanTitle = trim(strip_tags($newTitle));
        if (mb_strlen($cleanTitle) < 2) {
            return;
        }
        $clean = $this->sanitizePhotoStudioParams(array_merge($clientParams, ['enabled' => true]));
        $this->photoStudioParams = $clean;
        $this->laserParams['bitmap_mode'] = $this->mapStudioDitherModeToBitmapMode((string) $clean['dither_mode']);

        $saved = LaserQrCodeService::saveCustomStudioPreset($cleanTitle, $this->laserParams, $this->surfaceTone);
        $this->selectedStudioPresetId = (string) $saved['id'];
        unset($this->studioPresets);
        $this->photoStudioProfiles = $this->buildPhotoStudioProfilesList();
        $this->photoStudioActiveProfileId = $this->selectedStudioPresetId;
        $this->saveMetadataOnly(silent: true);

        $this->dispatch(
            'laser-studio-profiles-synced',
            profiles: $this->photoStudioProfiles,
            activeProfileId: $this->photoStudioActiveProfileId
        );
    }

    public function saveLaserPhotoStudio(?array $clientParams = null): void
    {
        $this->assertStaffAccess();
        if (!$this->isLaserPhotoStudioOpen || !$this->activeProjectId || !$this->photoStudioFileKey) {
            return;
        }

        if (is_array($clientParams)) {
            $this->photoStudioParams = $this->sanitizePhotoStudioParams(
                array_merge($this->photoStudioParams, $clientParams, ['enabled' => true])
            );
        }

        $project = LaserStudioProject::find($this->activeProjectId);
        if (!$project) {
            return;
        }

        $verifiedKey = $this->normalizeStudioRelPath($this->photoStudioFileKey);
        $conf = is_array($project->configuration) ? $project->configuration : [];
        $origRel = $this->photoStudioOriginalRelPath ?: LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($verifiedKey, $conf);
        $sourceAbsPath = $this->resolveStudioStorageAbsPath($origRel) ?? $this->resolveStudioStorageAbsPath($verifiedKey);
        if ($sourceAbsPath === null) {
            $this->notifyStatus('Quelldatei konnte für die Laser-Aufbereitung nicht geladen werden.', 'error');
            return;
        }

        $cleanParams = $this->sanitizePhotoStudioParams(array_merge($this->photoStudioParams, ['enabled' => true]));
        $savedRelPath = LaserPhotoPreprocessor::saveStudioProcessedLaserPng($sourceAbsPath, $cleanParams, 'bestellungen/xtool/photo-edits');

        if (!isset($conf['laser_photo_adjustments']) || !is_array($conf['laser_photo_adjustments'])) {
            $conf['laser_photo_adjustments'] = [];
        }

        $conf['laser_photo_adjustments'][$verifiedKey] = array_merge($cleanParams, [
            'enabled' => true,
            'original_path' => $origRel,
            'edited_path' => $savedRelPath,
            'updated_at' => now()->toIso8601String(),
        ]);

        // Live-Vorschau-URL in den platzierten Logos des Projekts mitpflegen (value bleibt stabil für FileDownloadService!)
        $editedAbs = $this->resolveStudioStorageAbsPath($savedRelPath);
        $previewUrl = asset('storage/' . $savedRelPath) . '?v=' . (($editedAbs ? @filemtime($editedAbs) : null) ?: time());

        foreach (['logos', 'logos_back'] as $logoField) {
            if (!isset($conf[$logoField]) || !is_array($conf[$logoField])) {
                continue;
            }
            foreach ($conf[$logoField] as &$lItem) {
                if (is_array($lItem) && $this->normalizeStudioRelPath((string) ($lItem['value'] ?? '')) === $verifiedKey) {
                    $lItem['url'] = $previewUrl;
                    $lItem['studio_edited'] = true;
                }
            }
            unset($lItem);
        }

        $project->configuration = $conf;
        $project->save();

        $this->logos = array_values(is_array($conf['logos'] ?? null) ? $conf['logos'] : []);
        $this->logosBack = array_values(is_array($conf['logos_back'] ?? null) ? $conf['logos_back'] : []);
        $this->laserParams['bitmap_mode'] = $this->mapStudioDitherModeToBitmapMode((string) $cleanParams['dither_mode']);
        $this->saveMetadataOnly(silent: true);

        $this->uploadedFilesRevision++;
        $this->closeLaserPhotoStudio();

        $this->dispatch(
            'laser-freehand-photo-edited',
            fileKey: $verifiedKey,
            previewUrl: $previewUrl
        );
    }

    protected function notifyStatus(string $msg, string $type = 'success'): void
    {
        $this->statusNotice = $msg;
        $this->statusNoticeType = $type;
    }

    public function render()
    {
        return view('livewire.shop.product.laser-studio-workspace');
    }
}

<?php

namespace App\Services\AI\Workspace;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiWorkspaceDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DateitrichterService
{
    /**
     * Zulässige Hauptordner im Workspace
     */
    public const MAIN_FOLDERS = [
        'Berufsleben',
        'Dokumente',
        'Gesundheit'
    ];

    /**
     * Führt den Dateitrichter aus.
     * Analysiert Dateien, verschiebt sie in die logische 3-Ordner-Struktur (nur einteilige Unterordner)
     * und verankert die Metadaten in der Datenbank und Knowledge Base.
     *
     * @param bool $processAll If true, also inspects and indexes subfolder files. If false, processes files in workspace root / inbox.
     * @return array
     */
    public function processAll(bool $processAll = false): array
    {
        $workspace = Storage::disk('workspace');
        $processed = [];
        $moved = 0;
        $indexed = 0;

        // Sicherstellen, dass die 3 Hauptordner existieren
        foreach (self::MAIN_FOLDERS as $mainFolder) {
            $path = 'agenten/workspace/' . $mainFolder;
            if (!$workspace->exists($path)) {
                $workspace->makeDirectory($path);
            }
        }

        // Dateien im Workspace-Hauptverzeichnis (oder bei processAll im gesamten Baum) ermitteln
        $files = $processAll 
            ? $workspace->allFiles('agenten/workspace') 
            : $workspace->files('agenten/workspace');

        foreach ($files as $file) {
            // Ignoriere Windows Zone.Identifier oder versteckte Dateien
            if (str_contains($file, ':Zone.Identifier') || str_starts_with(basename($file), '.')) {
                continue;
            }

            $result = $this->processSingleFile($file);
            if ($result) {
                $processed[] = $result;
                if ($result['moved']) {
                    $moved++;
                }
                $indexed++;
            }
        }

        return [
            'success' => true,
            'total_files_found' => count($files),
            'processed_count' => count($processed),
            'moved_count' => $moved,
            'indexed_count' => $indexed,
            'details' => $processed,
            'message' => count($processed) > 0 
                ? "Dateitrichter erfolgreich: {$indexed} Dokumente verarbeitet ({$moved} einsortiert) und in der Knowledge Base verankert."
                : "Keine neuen oder unsortierten Dokumente im Workspace-Eingang gefunden."
        ];
    }

    /**
     * Verarbeitet eine einzelne Datei im Workspace
     */
    public function processSingleFile(string $filePath): ?array
    {
        $workspace = Storage::disk('workspace');
        if (!$workspace->exists($filePath)) {
            return null;
        }

        $fullPath = $workspace->path($filePath);
        $filename = basename($filePath);
        $fileSize = $workspace->size($filePath);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $sha256 = hash_file('sha256', $fullPath);

        // 1. Textinhalt extrahieren
        $extractedText = $this->extractTextContent($fullPath, $extension);
        $searchString = strtolower($filename . ' ' . substr($extractedText, 0, 5000));

        // 2. Intelligente Klassifizierung (Hauptordner & einteiliger Unterordner)
        $classification = $this->classifyDocument($filename, $searchString, $extractedText);
        $targetFolder = 'agenten/workspace/' . $classification['main_folder'] . '/' . $classification['sub_folder'];
        $targetPath = $targetFolder . '/' . $filename;

        $wasMoved = false;
        $currentFolder = dirname($filePath);

        // Wenn die Datei noch im Root liegt oder falsch einsortiert ist: verschieben!
        if ($filePath !== $targetPath) {
            if (!$workspace->exists($targetFolder)) {
                $workspace->makeDirectory($targetFolder);
            }

            // Vermeide Überschreiben falls Datei am Zielort existiert
            if ($workspace->exists($targetPath) && $filePath !== $targetPath) {
                $uniquePrefix = date('Ymd_His_');
                $targetPath = $targetFolder . '/' . $uniquePrefix . $filename;
            }

            $workspace->move($filePath, $targetPath);
            $filePath = $targetPath;
            $wasMoved = true;
        }

        // 3. Extrahierte Metadaten verfeinern
        $extractedDate = $this->extractDate($filename, $extractedText);
        $title = $this->generateCleanTitle($filename, $extractedText, $classification);
        $purpose = $this->determinePurpose($classification, $searchString);
        $keyFacts = $this->extractKeyFacts($extractedText, $filename);
        $summary = $this->generateSummary($filename, $extractedText, $classification);

        // 4. In `ai_workspace_documents` registrieren / aktualisieren
        $doc = AiWorkspaceDocument::updateOrCreate(
            ['file_path' => $filePath],
            [
                'sha256' => $sha256,
                'filename' => basename($filePath),
                'file_type' => $extension,
                'file_size' => $fileSize,
                'title' => $title,
                'category' => $classification['category'],
                'tags' => $classification['tags'],
                'purpose' => $purpose,
                'summary' => $summary,
                'key_facts' => $keyFacts,
                'extracted_date' => $extractedDate,
                'content_preview' => mb_substr(trim($extractedText), 0, 1000),
                'full_text' => $extractedText ?: null,
            ]
        );

        // 5. In der Knowledge Base (`AiKnowledgeBase`) verankern
        $this->syncToKnowledgeBase($doc, $classification);

        return [
            'original_path' => $filePath,
            'current_path' => $filePath,
            'filename' => basename($filePath),
            'title' => $title,
            'category' => $classification['category'],
            'main_folder' => $classification['main_folder'],
            'sub_folder' => $classification['sub_folder'],
            'moved' => $wasMoved,
            'extracted_date' => $extractedDate ? $extractedDate->format('d.m.Y') : null,
        ];
    }

    /**
     * Textinhalt aus PDF, Text, MD oder CSV extrahieren
     */
    protected function extractTextContent(string $fullPath, string $extension): string
    {
        if (in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'html'])) {
            return (string) File::get($fullPath);
        }

        if ($extension === 'pdf') {
            // Schnellster und robustester Weg: pdftotext
            $cmd = 'pdftotext -layout ' . escapeshellarg($fullPath) . ' - 2>/dev/null';
            $output = shell_exec($cmd);
            if (!empty($output)) {
                return trim($output);
            }
        }

        return '';
    }

    /**
     * Klassifiziert das Dokument in einen der 3 Hauptordner und einen einteiligen Unterordner
     */
    protected function classifyDocument(string $filename, string $searchString, string $text): array
    {
        $s = $searchString;

        // -------------------------------------------------------------
        // A. GESUNDHEIT
        // -------------------------------------------------------------
        if (
            str_contains($s, 'bkk') || str_contains($s, 'firmus') || str_contains($s, 'krankengeld') ||
            str_contains($s, 'krankenkasse') || str_contains($s, 'arbeitsunfähigkeit') || str_contains($s, 'eau') ||
            str_contains($s, 'mdk') || str_contains($s, 'medizinischer dienst') || str_contains($s, 'lubos') ||
            str_contains($s, 'dr. schmidt') || str_contains($s, 'dorothea jung') || str_contains($s, 'transsexual') ||
            str_contains($s, 'f64') || str_contains($s, 'ga-op') || str_contains($s, 'neovagina') ||
            str_contains($s, 'orchiektomie') || str_contains($s, 'penektomie') || str_contains($s, 'hairfree') ||
            str_contains($s, 'epilation') || str_contains($s, 'hormon') || str_contains($s, 'endokrinolog') ||
            str_contains($s, 'rezept') || str_contains($s, 'diagnose') || str_contains($s, 'patient')
        ) {
            $mainFolder = 'Gesundheit';

            // Einteilige Subfolder-Ermittlung
            if (str_contains($s, 'lubos') || str_contains($s, 'klinik') || str_contains($s, 'hospital') || str_contains($s, 'stationär') || str_contains($s, 'operation') || str_contains($s, 'op-bericht') || str_contains($s, 'liegebescheinigung')) {
                $subFolder = 'Klinik';
                $category = 'Krankenhaus & Stationär';
                $tags = ['Klinik', 'Dr. Lubos', 'GA-OP', 'Stationär'];
            } elseif (str_contains($s, 'attest') || str_contains($s, 'gutachten') || str_contains($s, 'befund') || str_contains($s, 'medizinischer dienst') || str_contains($s, 'mdk')) {
                $subFolder = 'Atteste';
                $category = 'MDK-Begutachtungen & Atteste';
                $tags = ['Attest', 'Hausarzt', 'Medizinischer Dienst', 'Befund'];
            } elseif (str_contains($s, 'nachweis') || str_contains($s, 'einschreiben') || str_contains($s, 'einlieferungsbeleg') || str_contains($s, 'sendungsverfolgung') || str_contains($s, 'foto')) {
                $subFolder = 'Nachweise';
                $category = 'Gesundheit & Sozialrecht (BKK firmus)';
                $tags = ['Nachweise', 'Beleg', 'Einschreiben', 'BKK firmus'];
            } elseif (str_contains($s, 'krankengeld') || str_contains($s, 'widerspruch') || str_contains($s, 'aussteuerung') || str_contains($s, 'sgb v') || str_contains($s, 'leistungsfortzahlung')) {
                $subFolder = 'Krankengeld';
                $category = 'Krankengeld & Entgeltersatz';
                $tags = ['Krankengeld', 'BKK firmus', 'Widerspruch', 'Aussteuerung'];
            } else {
                $subFolder = 'Krankenkasse';
                $category = 'Gesundheit & Sozialrecht (BKK firmus)';
                $tags = ['Krankenkasse', 'BKK firmus', 'Schriftverkehr'];
            }

            return [
                'main_folder' => $mainFolder,
                'sub_folder' => $subFolder,
                'category' => $category,
                'tags' => $tags,
            ];
        }

        // -------------------------------------------------------------
        // B. BERUFSLEBEN
        // -------------------------------------------------------------
        if (
            str_contains($s, 'arbeitsamt') || str_contains($s, 'agentur für arbeit') || str_contains($s, 'bundesagentur') ||
            str_contains($s, 'gründungszuschuss') || str_contains($s, 'businessplan') || str_contains($s, 'liquidität') ||
            str_contains($s, 'seelenfunke') || str_contains($s, 'tragfähigkeit') || str_contains($s, 'lebenslauf') ||
            str_contains($s, 'gewerbe') || str_contains($s, 'alg 1') || str_contains($s, 'arbeitslosengeld') ||
            str_contains($s, 'grandke') || str_contains($s, 'fachkundige') || str_contains($s, 'projekt') ||
            str_contains($s, 'gründer') || str_contains($s, '150 tage') || str_contains($s, 'existenz')
        ) {
            $mainFolder = 'Berufsleben';

            if (str_contains($s, 'arbeitsamt') || str_contains($s, 'agentur für arbeit') || str_contains($s, 'bundesagentur') || str_contains($s, 'vermittlung') || str_contains($s, 'onlineportal_export') || str_contains($s, 'kurzantrag_arbeitsagentur')) {
                $subFolder = 'Arbeitsamt';
                $category = 'Existenzgründung & Arbeitsagentur';
                $tags = ['Arbeitsamt', 'Agentur für Arbeit', 'ALG 1', 'Mitteilungen'];
            } elseif (str_contains($s, 'businessplan') || str_contains($s, 'liquidität') || str_contains($s, 'tragfähigkeit') || str_contains($s, 'seelenfunke') || str_contains($s, 'lebenslauf') || str_contains($s, 'gründer')) {
                $subFolder = 'Existenzgruendung';
                $category = 'Existenzgründung & Arbeitsagentur';
                $tags = ['Existenzgründung', 'Mein Seelenfunke', 'Businessplan', 'Gründungszuschuss'];
            } else {
                $subFolder = 'Projekte';
                $category = 'Arbeit & Projekte';
                $tags = ['Projekte', 'Berufsleben', 'Dokumentation'];
            }

            return [
                'main_folder' => $mainFolder,
                'sub_folder' => $subFolder,
                'category' => $category,
                'tags' => $tags,
            ];
        }

        // -------------------------------------------------------------
        // C. DOKUMENTE (Finanzen, Steuern, Bank, Allgemein)
        // -------------------------------------------------------------
        $mainFolder = 'Dokumente';

        if (str_contains($s, 'bank') || str_contains($s, 'kontoauszug') || str_contains($s, 'umsaetze') || str_contains($s, 'sparkasse') || str_contains($s, 'iban') || str_contains($s, 'saldo')) {
            $subFolder = 'Bank';
            $category = 'Finanzen & Verträge';
            $tags = ['Bank', 'Kontoauszug', 'Umsätze'];
        } elseif (str_contains($s, 'steuer') || str_contains($s, 'finanzamt') || str_contains($s, 'elster') || str_contains($s, 'gewerbesteuer')) {
            $subFolder = 'Steuern';
            $category = 'Finanzen & Verträge';
            $tags = ['Steuern', 'Finanzamt', 'Steuererklärung'];
        } elseif (str_contains($s, 'rechnung') || str_contains($s, 'mahnung') || str_contains($s, 'finanzbericht') || str_contains($s, 'vertrag')) {
            $subFolder = 'Finanzen';
            $category = 'Finanzen & Verträge';
            $tags = ['Finanzen', 'Rechnung', 'Mahnung', 'Vertrag'];
        } elseif (str_contains($s, 'bericht') || str_contains($s, 'analyse') || str_contains($s, 'struktur')) {
            $subFolder = 'Berichte';
            $category = 'Allgemein';
            $tags = ['Berichte', 'Analysen', 'System'];
        } else {
            $subFolder = 'Allgemein';
            $category = 'Allgemein';
            $tags = ['Dokumente', 'Allgemein'];
        }

        return [
            'main_folder' => $mainFolder,
            'sub_folder' => $subFolder,
            'category' => $category,
            'tags' => $tags,
        ];
    }

    /**
     * Datum aus Dateiname oder Inhalt extrahieren
     */
    protected function extractDate(string $filename, string $text): ?Carbon
    {
        // 1. Suche im Dateinamen nach YYYY-MM-DD oder DD-MM-YYYY oder YYYYMMDD
        if (preg_match('/(202[0-9])[-_](0[1-9]|1[0-2])[-_](0[1-9]|[12][0-9]|3[01])/', $filename, $m)) {
            return Carbon::createFromFormat('Y-m-d', "{$m[1]}-{$m[2]}-{$m[3]}");
        }
        if (preg_match('/(0[1-9]|[12][0-9]|3[01])[-_](0[1-9]|1[0-2])[-_](202[0-9])/', $filename, $m)) {
            return Carbon::createFromFormat('d-m-Y', "{$m[1]}-{$m[2]}-{$m[3]}");
        }

        // 2. Suche im Text nach Datum (z.B. "Datum: 01.10.2026" oder "01.10.2026")
        if (!empty($text)) {
            if (preg_match('/(?:Datum|vom|Stand|Gültig ab)[:\s]+(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])\.(202[0-9])/i', $text, $m)) {
                return Carbon::createFromFormat('d.m.Y', "{$m[1]}.{$m[2]}.{$m[3]}");
            }
            if (preg_match('/(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])\.(202[0-9])/', $text, $m)) {
                return Carbon::createFromFormat('d.m.Y', "{$m[1]}.{$m[2]}.{$m[3]}");
            }
        }

        return null;
    }

    /**
     * Erzeugt einen sauberen, lesbaren Titel
     */
    protected function generateCleanTitle(string $filename, string $text, array $classification): string
    {
        // Wenn Markdown erste Überschrift hat
        if (preg_match('/^#\s+(.+)$/m', $text, $m)) {
            return trim($m[1]);
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $clean = str_replace(['_', '-'], ' ', $base);
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Numerische BKK Dokumente aufwerten
        if (is_numeric($base) && strlen($base) >= 10) {
            return "BKK firmus Bescheid / Mitteilung (Ref: {$base})";
        }

        return ucwords(trim($clean));
    }

    /**
     * Bestimmt den konkreten Zweck des Dokuments
     */
    protected function determinePurpose(array $classification, string $searchString): string
    {
        if ($classification['main_folder'] === 'Gesundheit') {
            if ($classification['sub_folder'] === 'Klinik') {
                return 'Stationäre Operations- und Liegedokumentation der Fachklinik zur geschlechtsangleichenden Operation.';
            }
            if ($classification['sub_folder'] === 'Krankengeld') {
                return 'Rechtliches Verfahrensdokument zur Durchsetzung des gesetzlichen Krankengeldanspruchs gegen die BKK firmus.';
            }
            if ($classification['sub_folder'] === 'Atteste') {
                return 'Fachärztliche Bescheinigung zur sozialmedizinischen Kausalität und postoperativen Arbeitsunfähigkeit.';
            }
            return 'Offizielle Korrespondenz und Mitteilung der Krankenkasse BKK firmus.';
        }

        if ($classification['main_folder'] === 'Berufsleben') {
            if ($classification['sub_folder'] === 'Existenzgruendung') {
                return 'Unterlage zur Existenzgründung Mein Seelenfunke für den Gründungszuschuss (§ 93 SGB III) und Tragfähigkeit.';
            }
            if ($classification['sub_folder'] === 'Arbeitsamt') {
                return 'Bescheid- und Vorgangsdokumentation der Bundesagentur für Arbeit (Leistungsfortzahlung und Nahtlosigkeit).';
            }
            return 'Projekt- und Arbeitsdokumentation im Berufsleben.';
        }

        if ($classification['sub_folder'] === 'Bank') {
            return 'Bankumsatzaufstellung und Kontonachweis zur Darlegung finanzieller Zu- und Abflüsse.';
        }
        if ($classification['sub_folder'] === 'Steuern') {
            return 'Steuerliche Unterlage und Steuererklärung gegenüber dem Finanzamt.';
        }

        return 'Archiviertes Arbeits- und Vorgangsdokument im privaten Workspace.';
    }

    /**
     * Extrahiert Aktenzeichen, Beträge, Betroffene und Schlüsseldaten
     */
    protected function extractKeyFacts(string $text, string $filename): array
    {
        $facts = [];

        // Beträge suchen (z.B. 120,42 € oder 65,90 EUR)
        if (preg_match_all('/(\d{1,4}(?:[.,]\d{2})?)\s*(?:€|EUR)/i', $text, $matches)) {
            $uniqueAmounts = array_unique(array_slice($matches[0], 0, 5));
            if (!empty($uniqueAmounts)) {
                $facts['betraege'] = implode(', ', $uniqueAmounts);
            }
        }

        // Aktenzeichen / Versichertennummer
        if (preg_match('/O[0-9]{9}/', $text, $m)) {
            $facts['versichertennummer'] = $m[0];
        }
        if (preg_match('/241D[0-9]{6}/', $text, $m)) {
            $facts['kundennummer_ba'] = $m[0];
        }
        if (preg_match('/Ref[:\s]+([0-9]{10,15})/i', $text, $m)) {
            $facts['referenznummer'] = $m[1];
        }

        // Paragraphen
        if (preg_match_all('/§+\s*\d+[a-z]?\s*(?:Abs\.\s*\d+)?\s*(?:SGB\s+[IVX]+|BGB|SGG)/i', $text, $m)) {
            $facts['rechtsgrundlagen'] = implode(', ', array_unique(array_slice($m[0], 0, 4)));
        }

        return $facts;
    }

    /**
     * Erzeugt eine prägnante Zusammenfassung
     */
    protected function generateSummary(string $filename, string $text, array $classification): string
    {
        if (empty($text)) {
            return "Dokument {$filename} im Bereich {$classification['main_folder']}/{$classification['sub_folder']} registriert.";
        }

        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $usefulLines = array_slice($lines, 0, 4);
        $summary = implode(' ', $usefulLines);
        $summary = preg_replace('/\s+/', ' ', $summary);

        return mb_substr($summary, 0, 300) . (mb_strlen($summary) > 300 ? '...' : '');
    }

    /**
     * Synchronisiert relevante Dokumente in die Knowledge Base
     */
    protected function syncToKnowledgeBase(AiWorkspaceDocument $doc, array $classification): void
    {
        // Nur Dokumente mit aussagekräftigem Inhalt synchronisieren
        if (empty($doc->full_text) && empty($doc->summary)) {
            return;
        }

        // Finde oder erstelle die Knowledge Base Kategorie
        $catName = match ($classification['main_folder']) {
            'Gesundheit' => 'Gesundheit & Sozialrecht (BKK firmus)',
            'Berufsleben' => 'Existenzgründung & Arbeitsagentur',
            default => 'Workspace-Dokumentenkatalog'
        };

        $category = AiKnowledgeBaseCategory::firstOrCreate(
            ['name' => $catName],
            ['slug' => Str::slug($catName)]
        );

        // Erstelle einen eindeutigen Knowledge Base Eintrag für wichtige Bescheide / Schreiben
        $kbSlug = 'dok-' . Str::slug(pathinfo($doc->filename, PATHINFO_FILENAME));
        if (strlen($kbSlug) > 100) {
            $kbSlug = substr($kbSlug, 0, 90) . '-' . substr($doc->sha256, 0, 8);
        }

        $content = "# " . $doc->title . "\n\n";
        $content .= "**Kategorie:** " . $classification['main_folder'] . " > " . $classification['sub_folder'] . "\n";
        $content .= "**Datei:** `" . $doc->file_path . "`\n";
        if ($doc->extracted_date) {
            $content .= "**Datum:** " . $doc->extracted_date->format('d.m.Y') . "\n";
        }
        $content .= "**Zweck:** " . $doc->purpose . "\n\n";
        $content .= "### Zusammenfassung\n" . $doc->summary . "\n\n";

        if (!empty($doc->key_facts)) {
            $content .= "### Eckdaten & Fakten\n";
            foreach ($doc->key_facts as $k => $v) {
                $content .= "- **" . ucfirst($k) . ":** " . $v . "\n";
            }
            $content .= "\n";
        }

        if (!empty($doc->content_preview)) {
            $content .= "### Auszug aus dem Dokument\n```text\n" . $doc->content_preview . "\n```\n";
        }

        $kb = AiKnowledgeBase::updateOrCreate(
            ['slug' => $kbSlug],
            [
                'title' => $doc->title,
                'ai_knowledge_base_category_id' => $category->id,
                'content' => $content,
                'is_published' => true,
            ]
        );

        // Tags verknüpfen
        if (!empty($doc->tags)) {
            $tagIds = [];
            foreach ($doc->tags as $tagName) {
                $tag = AiKnowledgeBaseTag::firstOrCreate(
                    ['name' => $tagName],
                    ['slug' => Str::slug($tagName)]
                );
                $tagIds[] = $tag->id;
            }
            $kb->tags()->sync($tagIds);
        }
    }
}

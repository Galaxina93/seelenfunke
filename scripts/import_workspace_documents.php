<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ai\AiWorkspaceDocument;
use App\Models\Ai\AiTool;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

echo "=== STARTE IMPORT DER WORKSPACE-DOKUMENTE IN SEELENFUNKE ===" . PHP_EOL;

$jsonPath = file_exists(__DIR__ . '/workspace_documents.json') ? __DIR__ . '/workspace_documents.json' : storage_path('app/workspace_documents.json');
if (!file_exists($jsonPath)) {
    die("FEHLER: $jsonPath existiert nicht!" . PHP_EOL);
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!is_array($data)) {
    die("FEHLER: Konnte JSON nicht parsen!" . PHP_EOL);
}

echo "Gefundene Dokumente im Katalog: " . count($data) . PHP_EOL;

$imported = 0;
$categories = [];

foreach ($data as $item) {
    // Normalisiere Tags
    $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
    $keyFacts = is_array($item['key_facts'] ?? null) ? $item['key_facts'] : [];
    
    // Bereinige evtl. leere Strings
    $extractedDate = !empty($item['extracted_date']) ? substr($item['extracted_date'], 0, 10) : null;
    if ($extractedDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $extractedDate)) {
        $extractedDate = null;
    }

    $doc = AiWorkspaceDocument::updateOrCreate(
        ['file_path' => $item['file_path']],
        [
            'sha256' => $item['sha256'] ?? null,
            'filename' => $item['filename'],
            'file_type' => $item['file_type'] ?? pathinfo($item['filename'], PATHINFO_EXTENSION),
            'file_size' => (int)($item['file_size'] ?? 0),
            'title' => $item['title'] ?? pathinfo($item['filename'], PATHINFO_FILENAME),
            'category' => $item['category'] ?? 'Allgemein',
            'tags' => $tags,
            'purpose' => $item['purpose'] ?? 'Dokumentiertes Arbeits- und Vorgangsdokument.',
            'summary' => $item['summary'] ?? '',
            'key_facts' => $keyFacts,
            'extracted_date' => $extractedDate,
            'content_preview' => $item['content_preview'] ?? null,
            'full_text' => $item['full_text'] ?? null,
        ]
    );

    $cat = $item['category'] ?? 'Allgemein';
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    $imported++;
}

echo "✓ Erfolgreich in `ai_workspace_documents` gespeichert: $imported Dokumente." . PHP_EOL;
echo "Kategorien-Übersicht:" . PHP_EOL;
foreach ($categories as $cat => $cnt) {
    echo "  - $cat: $cnt Dokumente" . PHP_EOL;
}

echo PHP_EOL . "=== REGISTRIERE WERKZEUGE (AI TOOLS) ===" . PHP_EOL;

$toolFind = AiTool::firstOrCreate(
    ['identifier' => 'workspace_find_documents'],
    [
        'name' => 'Workspace Find Documents',
        'description' => 'Sucht in der Datenbank nach registrierten Dokumenten im privaten Workspace anhand von Suchbegriff, Kategorie oder Zweck (wofür sie da sind). Gibt Metadaten, Pfad, Zweck und Relevanz zurück.'
    ]
);
echo "✓ Tool bereit: {$toolFind->name} ({$toolFind->identifier})" . PHP_EOL;

$toolInfo = AiTool::firstOrCreate(
    ['identifier' => 'workspace_get_document_info'],
    [
        'name' => 'Workspace Get Document Info',
        'description' => 'Liefert detaillierte Informationen zu einem bestimmten Dokument im Workspace: Wo es genau liegt, wofür es da ist (Zweck), Zusammenfassung, Eckdaten und Auszug.'
    ]
);
echo "✓ Tool bereit: {$toolInfo->name} ({$toolInfo->identifier})" . PHP_EOL;

// Tools allen relevanten Rollen zuweisen
$roles = AiRole::all();
foreach ($roles as $role) {
    if (!$role->tools()->where('ai_tool_id', $toolFind->id)->exists()) {
        $role->tools()->attach($toolFind->id);
    }
    if (!$role->tools()->where('ai_tool_id', $toolInfo->id)->exists()) {
        $role->tools()->attach($toolInfo->id);
    }
}
echo "✓ Tools an " . $roles->count() . " Rollen verknüpft (Dr. Funki, Buchi, Assistenz, etc.)." . PHP_EOL;

echo PHP_EOL . "=== MASTER-DOKUMENTENKATALOG IN KNOWLEDGE BASE ERSTELLEN ===" . PHP_EOL;

$kbCategory = AiKnowledgeBaseCategory::firstOrCreate(
    ['slug' => 'workspace-dokumente'],
    ['name' => 'Workspace-Dokumentenkatalog']
);

$catListText = "";
foreach ($categories as $cat => $cnt) {
    $docsInCat = AiWorkspaceDocument::where('category', $cat)->orderBy('filename')->get();
    $catListText .= "\n### Kategorie: {$cat} ({$cnt} Dokumente)\n\n";
    $catListText .= "| Dateiname | Zweck / Wofür da | Pfad | Datum |\n";
    $catListText .= "| :--- | :--- | :--- | :--- |\n";
    foreach ($docsInCat as $d) {
        $dateStr = $d->extracted_date ? $d->extracted_date->format('d.m.Y') : '–';
        $safePurpose = str_replace(["\r", "\n", "|"], " ", $d->purpose);
        $catListText .= "| `{$d->filename}` | {$safePurpose} | `{$d->file_path}` | {$dateStr} |\n";
    }
}

$kbContent = <<<MD
# Vollständiges Inventar & Katalog der Workspace-Dokumente

In diesem Master-Katalog ist jedes sensible und fachliche Dokument aus dem privaten Agenten-Workspace (`storage/app/private/agenten/workspace`) exakt katalogisiert.

Jeder KI-Agent (Dr. Funki, Buchi, Funkira) kann über diesen Katalog oder die Tools `workspace_find_documents` und `workspace_get_document_info` direkt nachvollziehen:
1. **Wo liegt das Dokument?** (Exakter Workspace-Pfad)
2. **Wofür ist es da?** (Zweck, Rechtsgrundlage, Relevanz für BKK firmus, Arbeitsagentur, Steuerberaterin oder Finanzen)
3. **Welche Eckdaten enthält es?** (Datum, Frist, Betrag, Aktenzeichen)

---

{$catListText}

MD;

$kbArticle = AiKnowledgeBase::updateOrCreate(
    ['slug' => 'master-dokumenten-katalog-workspace'],
    [
        'title' => 'Master-Dokumentenkatalog: Wo liegen Dokumente & Wofür sind sie da',
        'ai_knowledge_base_category_id' => $kbCategory->id,
        'content' => $kbContent,
        'is_published' => true,
    ]
);

$tagNames = ['Workspace', 'Dokumente', 'Katalog', 'BKK firmus', 'Existenzgründung', 'Finanz-Audit', 'Inventar'];
$syncTagIds = [];
foreach ($tagNames as $tn) {
    $t = AiKnowledgeBaseTag::firstOrCreate(
        ['slug' => Str::slug($tn)],
        ['name' => $tn]
    );
    $syncTagIds[] = $t->id;
}
$kbArticle->tags()->sync($syncTagIds);

echo "✓ Master-Dokumentenkatalog in Knowledge Base gespeichert (ID: {$kbArticle->id})." . PHP_EOL;

echo PHP_EOL . "=== DOKUMENTEN-MIGRATION & REGISTRIERUNG ERFOLGREICH BEENDET ===" . PHP_EOL;

<?php

namespace Tests\Feature\Ai;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Services\AI\Functions\AiBrainFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiBrainFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_to_brain_allows_short_notes_without_false_duplicate_rejection()
    {
        $cat = AiKnowledgeBaseCategory::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        // Long existing article that happens to contain "Guten Morgen"
        AiKnowledgeBase::create([
            'title' => 'Begrüßungsrituale im Team',
            'slug' => 'begruessungsrituale',
            'ai_knowledge_base_category_id' => $cat->id,
            'content' => 'Wir sagen jeden Tag Guten Morgen und besprechen unsere To-Dos.',
            'is_published' => true,
        ]);

        $dummyClass = new class {
            use AiBrainFuncs;
        };

        // Saving a new note whose content is just "Guten Morgen"
        $res = $dummyClass::executeSaveToBrain([
            'title' => 'Neue Grußformel',
            'content' => 'Guten Morgen',
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertDatabaseHas('ai_knowledge_bases', ['title' => 'Neue Grußformel']);
    }

    public function test_delete_brain_entry_prioritizes_title_and_protects_against_broad_accidental_deletions()
    {
        $cat = AiKnowledgeBaseCategory::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        $vitalPolicy = AiKnowledgeBase::create([
            'title' => 'Finanzrichtlinie des Unternehmens',
            'slug' => 'finanzrichtlinie',
            'ai_knowledge_base_category_id' => $cat->id,
            'content' => 'Hier stehen wichtige und vertrauliche Finanzregeln für das Unternehmen.',
            'is_published' => true,
        ]);

        $dummyClass = new class {
            use AiBrainFuncs;
        };

        // Querying for common 3-letter word "und" must NOT delete the vital policy
        $resBroad = $dummyClass::executeDeleteBrainEntry([
            'search_query' => 'und',
        ]);

        $this->assertEquals('error', $resBroad['status']);
        $this->assertDatabaseHas('ai_knowledge_bases', ['id' => $vitalPolicy->id]);

        // Targeted deletion by title succeeds
        $resTargeted = $dummyClass::executeDeleteBrainEntry([
            'search_query' => 'Finanzrichtlinie',
        ]);

        $this->assertEquals('success', $resTargeted['status']);
        $this->assertDatabaseMissing('ai_knowledge_bases', ['id' => $vitalPolicy->id]);
    }
}

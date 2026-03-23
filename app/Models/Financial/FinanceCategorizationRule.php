<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;

class FinanceCategorizationRule extends Model
{
    protected $fillable = [
        'admin_id',
        'search_term',
        'finance_category_id',
        'finance_cost_item_id',
        'amount_type',
        'amount_value',
        'priority'
    ];

    /**
     * Checks if a given transaction matches this rule.
     */
    public function matches(BankTransaction $tx): bool
    {
        // 1. Check amount value if explicitly set
        if ($this->amount_value !== null) {
            $expected = (float) $this->amount_value;
            $actual = (float) $tx->amount;
            // Match exactly or absolute value
            if ($expected !== $actual && $expected !== abs($actual)) {
                return false;
            }
        }

        // 2. Normalize and check Search Term
        if (empty($this->search_term)) {
            return false;
        }

        // Escape regex characters but allow spaces/hyphens
        $name = mb_strtolower(trim($tx->counterpart_name ?? ''));
        $purpose = mb_strtolower(trim($tx->purpose ?? ''));
        $searchTerm = mb_strtolower(trim($this->search_term));
        $combinedContext = $name . ' ' . $purpose;

        // Ensure we don't accidentally match 1-2 letter noise words blindly
        if (strlen($searchTerm) < 3) {
            // Require exact match for very short rules like "O2" (to avoid 'Co2' matching)
            return $name === $searchTerm || 
                   preg_match('/\b' . preg_quote($searchTerm, '/') . '\b/i', $combinedContext);
        }

        // Just perform a substring search for EVERY word in the search term.
        // Because "Gewerbesteuer Q1" gets scrubbed to "Gewerbesteuer", and "Kassenzeichen 123" to "Kassenzeichen",
        // the rule is "gewerbesteuer kassenzeichen". But the raw transaction still has "Q1 2026" sitting in between them!
        $keywords = array_filter(explode(' ', $searchTerm));
        foreach ($keywords as $kw) {
            if (!str_contains($combinedContext, $kw)) {
                return false;
            }
        }
        return true;
    }

    public function category()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function costItem()
    {
        return $this->belongsTo(FinanceCostItem::class, 'finance_cost_item_id');
    }
}

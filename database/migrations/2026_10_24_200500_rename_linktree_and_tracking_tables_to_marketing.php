<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'management_linktrees' => 'marketing_linktrees',
            'management_linktree_visits' => 'marketing_linktree_visits',
            'management_linktree_clicks' => 'marketing_linktree_clicks',
            'management_tracking_links' => 'marketing_tracking_links',
            'management_tracking_link_hits' => 'marketing_tracking_link_hits',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && !Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down(): void
    {
        $renames = [
            'marketing_tracking_link_hits' => 'management_tracking_link_hits',
            'marketing_tracking_links' => 'management_tracking_links',
            'marketing_linktree_clicks' => 'management_linktree_clicks',
            'marketing_linktree_visits' => 'management_linktree_visits',
            'marketing_linktrees' => 'management_linktrees',
        ];

        foreach ($renames as $new => $old) {
            if (Schema::hasTable($new) && !Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};

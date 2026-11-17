<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_gamifications')) {
            return;
        }

        Schema::table('customer_gamifications', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_gamifications', 'magma_unlocked_level')) {
                $table->unsignedTinyInteger('magma_unlocked_level')->default(1)->after('level');
            }
            if (!Schema::hasColumn('customer_gamifications', 'magma_hearts')) {
                $table->unsignedTinyInteger('magma_hearts')->default(6)->after('magma_unlocked_level');
            }
            if (!Schema::hasColumn('customer_gamifications', 'magma_active_run_id')) {
                $table->string('magma_active_run_id', 64)->nullable()->after('magma_hearts');
            }
            if (!Schema::hasColumn('customer_gamifications', 'magma_run_committed')) {
                $table->boolean('magma_run_committed')->default(false)->after('magma_active_run_id');
            }
        });

        DB::table('customer_gamifications')
            ->select(['id', 'titles_progress'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $progress = [];
                    if (is_string($row->titles_progress) && $row->titles_progress !== '') {
                        $decoded = json_decode($row->titles_progress, true);
                        if (is_array($decoded)) {
                            $progress = $decoded;
                        }
                    } elseif (is_array($row->titles_progress)) {
                        $progress = $row->titles_progress;
                    }

                    $rawCleared = $progress['lava_cleared_levels'] ?? [];
                    $validLevels = [];
                    if (is_array($rawCleared)) {
                        foreach ($rawCleared as $val) {
                            if (is_int($val) || (is_string($val) && ctype_digit($val))) {
                                $lvl = (int) $val;
                                if ($lvl >= 1 && $lvl <= 10) {
                                    $validLevels[$lvl] = true;
                                }
                            }
                        }
                    }

                    $maxSequential = 0;
                    for ($lvl = 1; $lvl <= 10; $lvl++) {
                        if (isset($validLevels[$lvl])) {
                            $maxSequential = $lvl;
                        } else {
                            break;
                        }
                    }

                    $unlockedLevel = max(1, min(10, $maxSequential + 1));

                    DB::table('customer_gamifications')
                        ->where('id', $row->id)
                        ->update([
                            'magma_unlocked_level' => $unlockedLevel,
                            'magma_hearts' => 6,
                            'magma_active_run_id' => null,
                            'magma_run_committed' => false,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_gamifications')) {
            return;
        }

        Schema::table('customer_gamifications', function (Blueprint $table) {
            $dropCols = [];
            foreach (['magma_unlocked_level', 'magma_hearts', 'magma_active_run_id', 'magma_run_committed'] as $col) {
                if (Schema::hasColumn('customer_gamifications', $col)) {
                    $dropCols[] = $col;
                }
            }
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }
};

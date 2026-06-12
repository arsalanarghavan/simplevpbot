<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WpImportVerifier
{
    /**
     * @return array{ok:bool, rows:array<int, array{table:string, source:int, target:int, match:bool}>}
     */
    public function verify(WpDumpData $dump): array
    {
        $rows = [];
        $ok = true;

        foreach ($dump->tableCounts() as $table => $sourceCount) {
            if (! Schema::hasTable($table)) {
                $rows[] = [
                    'table' => $table,
                    'source' => $sourceCount,
                    'target' => -1,
                    'match' => false,
                ];
                $ok = false;
                continue;
            }
            $targetCount = (int) DB::table($table)->count();
            $match = $sourceCount === $targetCount;
            if (! $match) {
                $ok = false;
            }
            $rows[] = [
                'table' => $table,
                'source' => $sourceCount,
                'target' => $targetCount,
                'match' => $match,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['table'], $b['table']));

        return ['ok' => $ok, 'rows' => $rows];
    }
}

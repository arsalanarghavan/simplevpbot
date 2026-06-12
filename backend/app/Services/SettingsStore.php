<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsStore
{
    protected const CACHE_KEY = 'svp_settings_all';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $encoded = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);

        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => $key],
            ['value' => $encoded, 'updated_at' => now()]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $rows = DB::table('svp_settings')->get(['key_name', 'value']);
            $out = [];
            foreach ($rows as $row) {
                $decoded = json_decode($row->value, true);
                $out[$row->key_name] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
            }

            return $out;
        });
    }

    /** @param  array<string, mixed>  $patch */
    public function merge(array $patch): void
    {
        foreach ($patch as $key => $value) {
            $this->set($key, $value);
        }
    }
}

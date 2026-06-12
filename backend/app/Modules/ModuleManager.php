<?php

namespace App\Modules;

class ModuleManager
{
    /** @var array<string, bool> */
    protected array $enabled = [];

    public function __construct()
    {
        foreach (config('modules.modules', []) as $key => $module) {
            $this->enabled[$key] = (bool) ($module['enabled'] ?? false);
        }
        $this->enabled['core'] = true;
    }

    public function isEnabled(string $key): bool
    {
        if ($key === 'core') {
            return true;
        }

        if (! ($this->enabled[$key] ?? false)) {
            return false;
        }

        foreach (config("modules.modules.{$key}.depends", []) as $dep) {
            if (! $this->isEnabled($dep)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function enabledKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->enabled),
            fn (string $key) => $this->isEnabled($key)
        ));
    }

    /** @return array<string, array{key: string, label: string, enabled: bool}> */
    public function manifest(): array
    {
        $out = [];
        foreach (config('modules.modules', []) as $key => $module) {
            $out[$key] = [
                'key' => $key,
                'label' => (string) ($module['label'] ?? $key),
                'enabled' => $this->isEnabled($key),
            ];
        }

        return $out;
    }
}

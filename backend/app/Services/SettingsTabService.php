<?php

namespace App\Services;

class SettingsTabService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @param  array<string, mixed>  $values */
    public function save(string $tab, array $values): bool
    {
        $tab = preg_replace('/[^a-z0-9_]/', '', strtolower($tab)) ?? '';
        if ($tab === '') {
            return false;
        }

        unset($values['tab'], $values['op'], $values['settings_tab']);
        foreach ($values as $key => $value) {
            $this->settings->set("{$tab}.{$key}", $value);
        }

        return true;
    }
}

<?php

namespace App\Services;

class PortalPagesBuilder
{
    public function __construct(protected SettingsStore $settings) {}

    /**
     * WP-compatible portal page choices for whitelabel selector.
     *
     * @return list<array{id:int, title:string}>
     */
    public function build(bool $isReseller): array
    {
        if ($isReseller) {
            return [];
        }

        $raw = $this->settings->get('portal_pages', null);
        if (is_array($raw) && $raw !== []) {
            $out = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $title = trim((string) ($row['title'] ?? ''));
                if ($id > 0 && $title !== '') {
                    $out[] = ['id' => $id, 'title' => $title];
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        $pageId = max(0, (int) $this->settings->get('portal_page_id', 0));
        if ($pageId > 0) {
            $title = trim((string) $this->settings->get('portal_page_title', 'Portal'));

            return [['id' => $pageId, 'title' => $title !== '' ? $title : 'Portal']];
        }

        return [];
    }
}

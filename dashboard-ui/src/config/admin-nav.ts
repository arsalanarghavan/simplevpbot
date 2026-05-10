import type { LucideIcon } from "lucide-react"
import {
  Activity,
  Bot,
  LayoutDashboard,
  Layers,
  PanelsTopLeft,
  Network,
  Server,
  Settings2,
  Users,
  Wallet,
} from "lucide-react"

/** Single top-level link in the sidebar. */
export type AdminNavLeaf = {
  kind: "leaf"
  tabKey: string
  icon: LucideIcon
}

/** Collapsible group of tabs (same pattern as legacy NavMain). */
export type AdminNavCollapsible = {
  kind: "collapsible"
  id: string
  icon: LucideIcon
  /** i18n key under translation, e.g. sidebar.groups.users */
  labelKey: string
  children: { tabKey: string; icon?: LucideIcon }[]
}

export type AdminNavEntry = AdminNavLeaf | AdminNavCollapsible

export type AdminNavSection = {
  id: string
  /** i18n: sidebar.sections.* */
  hintKey: string
  entries: AdminNavEntry[]
}

/** Tabs never shown to resellers (infra / global settings / alias monitoring). */
export const ADMIN_ONLY_TAB_KEYS = new Set<string>([
  "site_settings",
  "backup",
  "notifications",
  "logs",
  "xui_panels",
  "configs",
  "l2tp_servers",
  "wholesale_lines",
  "texts",
  "monitoring",
])

/**
 * Full admin nav minus forbidden tabs, intersected with server/permission allow-list.
 * Injects `reseller_bots` under Bot settings when allowed (not present on super-admin nav).
 */
export function filterAdminNavForReseller(
  sections: AdminNavSection[],
  allowedTabs: Set<string>
): AdminNavSection[] {
  const out: AdminNavSection[] = []
  for (const sec of sections) {
    const entries: AdminNavEntry[] = []
    for (const ent of sec.entries) {
      if (ent.kind === "leaf") {
        if (ADMIN_ONLY_TAB_KEYS.has(ent.tabKey)) continue
        if (!allowedTabs.has(ent.tabKey)) continue
        entries.push(ent)
      } else {
        const children = ent.children.filter(
          (ch) => !ADMIN_ONLY_TAB_KEYS.has(ch.tabKey) && allowedTabs.has(ch.tabKey)
        )
        if (children.length === 0) continue
        entries.push({ ...ent, children })
      }
    }
    if (entries.length === 0) continue
    out.push({ ...sec, entries })
  }

  if (!allowedTabs.has("reseller_bots")) {
    return reorderResellerNavSections(out, false)
  }

  const botIdx = out.findIndex((s) => s.id === "bot")
  if (botIdx < 0) return reorderResellerNavSections(out, false)

  const botSec = out[botIdx]!
  const newEntries = botSec.entries.map((ent) => {
    if (ent.kind === "collapsible" && ent.id === "bot_menu") {
      if (ent.children.some((c) => c.tabKey === "reseller_bots")) return ent
      return {
        ...ent,
        children: [...ent.children, { tabKey: "reseller_bots", icon: Bot }],
      }
    }
    return ent
  })
  out[botIdx] = { ...botSec, entries: newEntries }
  return reorderResellerNavSections(out, true)
}

const RESELLER_NAV_SECTION_ORDER = ["overview", "users", "finance", "bot", "settings"]

const RESELLER_BOT_CHILD_ORDER = ["plan_cats", "reseller_bots", "bot_ui"]

function reorderResellerNavSections(sections: AdminNavSection[], sortBotChildren: boolean): AdminNavSection[] {
  const sorted: AdminNavSection[] = []
  for (const id of RESELLER_NAV_SECTION_ORDER) {
    const found = sections.find((s) => s.id === id)
    if (found) sorted.push(found)
  }
  for (const s of sections) {
    if (!sorted.some((x) => x.id === s.id)) sorted.push(s)
  }
  if (!sortBotChildren) return sorted
  const botIdx = sorted.findIndex((s) => s.id === "bot")
  if (botIdx < 0) return sorted
  const botSec = sorted[botIdx]!
  const orderMap = new Map(RESELLER_BOT_CHILD_ORDER.map((k, i) => [k, i]))
  sorted[botIdx] = {
    ...botSec,
    entries: botSec.entries.map((ent) => {
      if (ent.kind === "collapsible" && ent.id === "bot_menu") {
        const children = [...ent.children].sort(
          (a, b) => (orderMap.get(a.tabKey) ?? 99) - (orderMap.get(b.tabKey) ?? 99)
        )
        return { ...ent, children }
      }
      return ent
    }),
  }
  return sorted
}

export const ADMIN_NAV_SECTIONS: AdminNavSection[] = [
  {
    id: "overview",
    hintKey: "sidebar.sections.overview",
    entries: [
      { kind: "leaf", tabKey: "dashboard", icon: LayoutDashboard },
      { kind: "leaf", tabKey: "monitoring", icon: Activity },
    ],
  },
  {
    id: "users",
    hintKey: "sidebar.sections.users",
    entries: [
      {
        kind: "collapsible",
        id: "users_menu",
        icon: Users,
        labelKey: "sidebar.groups.users",
        children: [
          { tabKey: "users" },
          { tabKey: "users_bulk" },
          { tabKey: "broadcast" },
          { tabKey: "resellers" },
        ],
      },
    ],
  },
  {
    id: "finance",
    hintKey: "sidebar.sections.finance",
    entries: [
      {
        kind: "collapsible",
        id: "finance_menu",
        icon: Wallet,
        labelKey: "sidebar.groups.finance",
        children: [
          { tabKey: "plans" },
          { tabKey: "cards" },
          { tabKey: "receipts" },
          { tabKey: "reseller_finance" },
          { tabKey: "referral" },
          { tabKey: "discounts" },
        ],
      },
    ],
  },
  {
    id: "bot",
    hintKey: "sidebar.sections.bot",
    entries: [
      {
        kind: "collapsible",
        id: "bot_menu",
        icon: Bot,
        labelKey: "sidebar.groups.botSettings",
        children: [
          { tabKey: "plan_cats" },
          { tabKey: "texts" },
          { tabKey: "bot_ui", icon: PanelsTopLeft },
          { tabKey: "bots" },
        ],
      },
    ],
  },
  {
    id: "settings",
    hintKey: "sidebar.sections.settings",
    entries: [
      {
        kind: "collapsible",
        id: "servers_menu",
        icon: Server,
        labelKey: "sidebar.groups.servers",
         children: [
          { tabKey: "xui_panels" },
          { tabKey: "configs", icon: Network },
          { tabKey: "l2tp_servers" },
          { tabKey: "wholesale_lines", icon: Layers },
        ],
      },
      {
        kind: "collapsible",
        id: "system_prefs_menu",
        icon: Settings2,
        labelKey: "sidebar.groups.systemPreferences",
        children: [
          { tabKey: "site_settings" },
          { tabKey: "backup" },
          { tabKey: "notifications" },
          { tabKey: "logs" },
        ],
      },
    ],
  },
]

/** Ordered tab keys for URL/boot compatibility (labels from i18n in SPA). */
export const ADMIN_TAB_KEYS: string[] = [
  "dashboard",
  "monitoring",
  "site_settings",
  "users",
  "resellers",
  "users_bulk",
  "broadcast",
  "plans",
  "cards",
  "receipts",
  "reseller_finance",
  "referral",
  "discounts",
  "plan_cats",
  "reseller_bots",
  "texts",
  "bot_ui",
  "notifications",
  "bots",
  "xui_panels",
  "configs",
  "l2tp_servers",
  "wholesale_lines",
  "backup",
  "logs",
]

export type SearchNavRow = {
  tabKey: string
  sectionHintKey: string
  /** When inside a collapsible, i18n key for the parent row */
  parentLabelKey?: string
}

/** Flat list for command palette: every navigable tab once per menu row (duplicate tabKeys allowed for search). */
export function flattenNavForSearch(): SearchNavRow[] {
  const rows: SearchNavRow[] = []
  for (const sec of ADMIN_NAV_SECTIONS) {
    for (const ent of sec.entries) {
      if (ent.kind === "leaf") {
        rows.push({ tabKey: ent.tabKey, sectionHintKey: sec.hintKey })
      } else {
        for (const ch of ent.children) {
          rows.push({
            tabKey: ch.tabKey,
            sectionHintKey: sec.hintKey,
            parentLabelKey: ent.labelKey,
          })
        }
      }
    }
  }
  return rows
}

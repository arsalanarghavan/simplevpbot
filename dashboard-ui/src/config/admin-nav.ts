import type { LucideIcon } from "lucide-react"
import {
  Activity,
  Bot,
  LayoutDashboard,
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
        children: [{ tabKey: "users" }, { tabKey: "broadcast" }],
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
          { tabKey: "panel_inbounds" },
          { tabKey: "configs", icon: Network },
          { tabKey: "l2tp_servers" },
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
  "broadcast",
  "plans",
  "cards",
  "receipts",
  "referral",
  "discounts",
  "plan_cats",
  "texts",
  "notifications",
  "bots",
  "xui_panels",
  "panel_inbounds",
  "configs",
  "l2tp_servers",
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

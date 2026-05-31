"use client"

import { Check, LayoutDashboard, LifeBuoy, MessageSquareQuote, UserRoundCog } from "lucide-react"
import type { MouseEvent } from "react"
import { useTranslation } from "react-i18next"

import { NavGrouped } from "@/components/nav-grouped"
import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import type { AdminNavSection } from "@/config/admin-nav"
import { cn } from "@/lib/utils"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { writeSiteSubtabToUrl } from "@/lib/site-settings-subtab"

type NavTab = {
  key: string
  label: string
}

const menuBtnCollapsedIcon =
  "group-data-[collapsible=icon]:justify-center [&>span]:group-data-[collapsible=icon]:hidden"

function SidebarQuickLinks({
  rtl,
  variant,
  dashboardBaseUrl,
  onSelectTab,
}: {
  rtl: boolean
  variant: "admin" | "reseller" | "user"
  dashboardBaseUrl: string
  onSelectTab: (tabKey: string) => void
}) {
  const { t } = useTranslation()
  const base = dashboardBaseUrl.replace(/\/?$/, "")

  const openSupportSettings = (e: MouseEvent) => {
    e.preventDefault()
    if (variant !== "admin") return
    writeSiteSubtabToUrl("whitelabel")
    onSelectTab("site_settings")
    const url = `${base}/site_settings/?site_subtab=whitelabel#whitelabel-support`
    window.history.pushState({ tab: "site_settings" }, "", url)
    window.setTimeout(() => {
      document.getElementById("whitelabel-support")?.scrollIntoView({ behavior: "smooth", block: "start" })
    }, 200)
  }

  const host = typeof window !== "undefined" ? window.location.hostname : "localhost"
  const resellerSupportHref = `mailto:support@${host}?subject=${encodeURIComponent(t("sidebar.footer.support"))}`

  return (
    <SidebarMenu
      className="border-b border-sidebar-border px-0 pb-3"
      dir={rtl ? "rtl" : undefined}
    >
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild={variant !== "admin"}
          size="default"
          tooltip={t("sidebar.footer.support")}
          className={menuBtnCollapsedIcon}
          onClick={variant === "admin" ? openSupportSettings : undefined}
        >
          {variant === "admin" ? (
            <>
              <LifeBuoy />
              <span className="truncate">{t("sidebar.footer.support")}</span>
            </>
          ) : (
            <a href={resellerSupportHref}>
              <LifeBuoy />
              <span className="truncate">{t("sidebar.footer.support")}</span>
            </a>
          )}
        </SidebarMenuButton>
      </SidebarMenuItem>
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild
          size="default"
          tooltip={t("sidebar.footer.feedback")}
          className={menuBtnCollapsedIcon}
        >
          <a href="#feedback" onClick={(e) => e.preventDefault()}>
            <MessageSquareQuote />
            <span className="truncate">{t("sidebar.footer.feedback")}</span>
          </a>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

function RoleSwitcher({
  activePersona,
  availablePersonas,
  restUrl,
  nonce,
  rtl = false,
  personaSwitchBlocked,
}: {
  activePersona: "admin" | "reseller" | "user"
  availablePersonas: Array<"admin" | "reseller" | "user">
  restUrl: string
  nonce: string
  rtl?: boolean
  /** True while impersonating a reseller — persona API returns 403 until stopped. */
  personaSwitchBlocked?: boolean
}) {
  const { t } = useTranslation()
  const label =
    activePersona === "admin"
      ? t("sidebar.role.admin")
      : activePersona === "reseller"
        ? t("sidebar.role.reseller")
        : t("sidebar.role.user")

  const setPersona = (persona: "admin" | "reseller" | "user") => {
    if (persona === activePersona) return
    const base = restUrl.replace(/\/$/, "")
    void fetch(`${base}/dashboard/persona`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": nonce,
      },
      credentials: "include",
      body: JSON.stringify({ persona }),
    })
      .then(async (r) => {
        const json = (await r.json()) as { ok?: boolean; code?: string }
        if (r.ok && json?.ok) window.location.reload()
      })
      .catch(() => {})
  }

  if (personaSwitchBlocked) {
    return (
      <TooltipProvider delayDuration={200}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span className="inline-flex">
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="h-8 w-8 shrink-0"
                disabled
                aria-label={label}
              >
                <UserRoundCog className="size-4 opacity-50" />
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent side="bottom" className={cn("max-w-xs", rtl && "text-right")}>
            <p>{t("layout.personaSwitchBlockedImpersonation")}</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    )
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8 shrink-0"
          aria-label={label}
          title={label}
        >
          <UserRoundCog className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="end"
        style={{ direction: rtl ? "rtl" : "ltr" }}
        className={cn("min-w-48", rtl && "text-right")}
      >
        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
          {t("sidebar.role.switchLabel")}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        {availablePersonas.includes("admin") ? (
          <DropdownMenuItem
            disabled={activePersona === "admin"}
            className={cn("gap-2 text-sm", rtl && "justify-end")}
            onClick={() => setPersona("admin")}
          >
            {activePersona === "admin" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.admin")}
          </DropdownMenuItem>
        ) : null}
        {availablePersonas.includes("reseller") ? (
          <DropdownMenuItem
            disabled={activePersona === "reseller"}
            className={cn("gap-2 text-sm", rtl && "justify-end")}
            onClick={() => setPersona("reseller")}
          >
            {activePersona === "reseller" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.reseller")}
          </DropdownMenuItem>
        ) : null}
        {availablePersonas.includes("user") ? (
          <DropdownMenuItem
            disabled={activePersona === "user"}
            className={cn("gap-2 text-sm", rtl && "justify-end")}
            onClick={() => setPersona("user")}
          >
            {activePersona === "user" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.user")}
          </DropdownMenuItem>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export function AppSidebar({
  side,
  variant,
  navTabs,
  user,
  activeTabKey,
  onSelectTab,
  dashboardBaseUrl,
  siteName,
  siteIconUrl,
  adminSections,
  activePersona,
  availablePersonas,
  personaRestUrl,
  personaNonce,
  personaSwitchBlocked = false,
}: {
  side: "left" | "right"
  variant: "admin" | "reseller" | "user"
  navTabs: NavTab[]
  user: {
    name: string
    tgUserId: number
    baleUserId: number
    avatar: string
    logoutUrl?: string
  }
  activeTabKey: string
  onSelectTab: (tabKey: string) => void
  dashboardBaseUrl: string
  siteName: string
  siteIconUrl?: string
  adminSections?: AdminNavSection[]
  activePersona?: "admin" | "reseller" | "user"
  availablePersonas?: Array<"admin" | "reseller" | "user">
  personaRestUrl?: string
  personaNonce?: string
  personaSwitchBlocked?: boolean
}) {
  const isFa = side === "right"
  const { t } = useTranslation()
  const base = dashboardBaseUrl.replace(/\/?$/, "")
  const subItemUrl = (tabKey: string) => buildDashboardTabUrl(base, tabKey)

  const displayName = siteName.trim() || t("sidebar.siteFallback")
  const isAdmin = variant === "admin"
  const persona: "admin" | "reseller" | "user" =
    activePersona ?? (isAdmin ? "admin" : variant === "reseller" ? "reseller" : "user")
  const personas = availablePersonas ?? [persona]
  const showRoleSwitcher =
    Boolean(personaRestUrl && personaNonce) &&
    (personas.length > 1 || personaSwitchBlocked)

  const userMainItems = [
    {
      title: t("myPanel"),
      url: `${base}/`,
      icon: LayoutDashboard,
      isActive: true,
      items: navTabs.map((tab) => ({
        title: tab.label,
        url: subItemUrl(tab.key),
        tabKey: tab.key,
      })),
    },
  ]

  const showOperatorHeader = variant === "admin" || variant === "reseller"

  return (
    <Sidebar side={side} collapsible="icon">
      {(showOperatorHeader || (variant === "user" && showRoleSwitcher)) && (
        <SidebarHeader
          className={cn(
            "gap-2 border-b border-sidebar-border pb-3",
            isFa && "text-right"
          )}
        >
          {isFa ? (
            <div dir="rtl" className="flex items-center gap-2 px-2 pt-2">
              {siteIconUrl ? (
                <img
                  src={siteIconUrl}
                  alt=""
                  className="size-8 shrink-0 rounded-md object-cover"
                />
              ) : null}
              <p className="min-w-0 flex-1 truncate text-sm font-semibold leading-tight">
                {displayName}
              </p>
              {showRoleSwitcher ? (
                <RoleSwitcher
                  activePersona={persona}
                  availablePersonas={personas}
                  restUrl={personaRestUrl!}
                  nonce={personaNonce!}
                  rtl={isFa}
                  personaSwitchBlocked={personaSwitchBlocked}
                />
              ) : null}
            </div>
          ) : (
            <div className="flex items-center gap-2 px-2 pt-2">
              {siteIconUrl ? (
                <img
                  src={siteIconUrl}
                  alt=""
                  className="size-8 shrink-0 rounded-md object-cover"
                />
              ) : null}
              <div className="flex min-w-0 flex-1 items-center gap-2">
                <p className="min-w-0 flex-1 truncate text-sm font-semibold leading-tight">
                  {displayName}
                </p>
                {showRoleSwitcher ? (
                  <RoleSwitcher
                    activePersona={persona}
                    availablePersonas={personas}
                    restUrl={personaRestUrl!}
                    nonce={personaNonce!}
                    rtl={isFa}
                    personaSwitchBlocked={personaSwitchBlocked}
                  />
                ) : null}
              </div>
            </div>
          )}
        </SidebarHeader>
      )}
      <SidebarContent>
        {variant === "admin" || variant === "reseller" ? (
          <NavGrouped
            activeTabKey={activeTabKey}
            onSelectTab={onSelectTab}
            subItemUrl={subItemUrl}
            rtl={isFa}
            sections={adminSections}
          />
        ) : (
          <NavMain
            items={userMainItems}
            groupLabel={t("myPanel")}
            activeTabKey={activeTabKey}
            onSubItemClick={onSelectTab}
            subItemUrl={subItemUrl}
            rtl={isFa}
          />
        )}
      </SidebarContent>
      <SidebarFooter className="gap-0">
        {(variant === "admin" || variant === "reseller") && (
          <SidebarQuickLinks
            rtl={isFa}
            variant={variant}
            dashboardBaseUrl={dashboardBaseUrl}
            onSelectTab={onSelectTab}
          />
        )}
        <div className={cn((variant === "admin" || variant === "reseller") && "px-0 pt-1")}>
          <NavUser user={user} rtl={isFa} />
        </div>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}

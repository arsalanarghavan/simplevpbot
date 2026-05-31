import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { Maximize, Minimize, Moon, Sun } from "lucide-react"
import { useTheme } from "next-themes"
import { AccentMenu } from "@/components/accent-menu"
import { AppSidebar } from "@/components/app-sidebar"
import { DashboardAdminView } from "@/components/dashboard-admin-view"
import type { ReceiptsListFilters } from "@/components/dashboard-receipts-admin"
import { BaleLogo } from "@/components/icons/bale-logo"
import { TelegramLogo } from "@/components/icons/telegram-logo"
import { ImpersonationBanner } from "@/components/impersonation-banner"
import { Button } from "@/components/ui/button"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import { DashboardLogin } from "@/components/dashboard-login"
import { ACCENT_BRANDING_VAR_KEYS, normalizeAccent } from "@/lib/accent"
import { botPlatformUrl } from "@/lib/bot-links"
import { buildAdminStateQuery } from "@/lib/dash-pagination"
import { buildDashboardTabUrl, mapTabForReseller, parseActiveDashTab, parseDashFromPath } from "@/lib/dash-tab"
import { resolveLegacyPlansTab } from "@/lib/plans-subview"
import { resolveLegacySiteTab, writeSiteSubtabToUrl } from "@/lib/site-settings-subtab"
import { formatNumber } from "@/lib/format-locale"
import {
  ADMIN_NAV_SECTIONS,
  filterAdminNavForReseller,
  type AdminNavSection,
} from "@/config/admin-nav"
import { cn } from "@/lib/utils"

type DashData = {
  navTabs?: NavTab[]
  user?: { label?: string }
} & Record<string, unknown>
type NavTab = { key: string; label: string }
type DashPersona = "admin" | "reseller" | "user"

const RESELLER_ALLOWED_BY_PERMISSION: Record<string, string | null> = {
  dashboard: null,
  monitoring: "services.manage",
  users: "users.manage",
  resellers: "users.manage",
  users_bulk: "users.bulk",
  plans: "plans.manage",
  plan_cats: "plans.manage",
  cards: "plans.manage",
  referral: "users.manage",
  referral_reports: "users.manage",
  discounts: "plans.manage",
  reseller_bots: "services.manage",
  bot_ui: "services.manage",
  broadcast: "broadcast.send",
  receipts: "receipts.review",
  reseller_charge: "plans.manage",
  reseller_workspace: null,
}

function App() {
  const { t, i18n } = useTranslation()
  const { theme, setTheme } = useTheme()
  const boot = useMemo(() => window.__SIMPLEVPBOT_DASH__ || {}, [])
  const uiAccent = normalizeAccent(boot.uiAccent)

  useEffect(() => {
    document.documentElement.setAttribute("data-accent", uiAccent)
  }, [uiAccent])

  useEffect(() => {
    const vars = (boot as { branding?: { cssVariables?: Record<string, string> } }).branding?.cssVariables
    if (!vars) return
    const root = document.documentElement
    const skipAccentVars = uiAccent !== "default"
    for (const [key, val] of Object.entries(vars)) {
      if (!val) continue
      if (skipAccentVars && ACCENT_BRANDING_VAR_KEYS.has(key)) continue
      root.style.setProperty(key, val)
    }
    return () => {
      for (const key of Object.keys(vars)) {
        root.style.removeProperty(key)
      }
    }
  }, [boot, uiAccent])

  const isAdmin = Boolean(boot.isAdmin)
  const isReseller = Boolean(boot.isReseller)
  const isOperator = isAdmin || isReseller
  const availablePersonas: DashPersona[] = useMemo(() => {
    const raw = boot.availablePersonas
    if (!Array.isArray(raw)) return []
    return raw.filter((x): x is DashPersona => x === "admin" || x === "reseller" || x === "user")
  }, [boot])
  const activePersona: DashPersona = useMemo(() => {
    const a = boot.activePersona
    if (a === "admin" || a === "reseller" || a === "user") return a
    return availablePersonas[0] ?? "user"
  }, [boot.activePersona, availablePersonas])
  const [data, setData] = useState<DashData | null>(null)
  const dashStateAbortRef = useRef<AbortController | null>(null)
  /** Query params for GET admin/state list pagination (e.g. users_page). */
  const [listQuery, setListQuery] = useState<Record<string, string>>({})

  const receiptsListFilters = useMemo(
    (): ReceiptsListFilters => ({
      q: listQuery.receipts_q ?? "",
      status: listQuery.receipts_status ?? "all",
      sort: listQuery.receipts_sort ?? "created_desc",
      dateFrom: listQuery.receipts_date_from ?? "",
      dateTo: listQuery.receipts_date_to ?? "",
      amountMin: listQuery.receipts_amount_min ?? "",
      amountMax: listQuery.receipts_amount_max ?? "",
    }),
    [listQuery]
  )

  const onReceiptsListFiltersChange = useCallback((patch: Partial<ReceiptsListFilters>) => {
    setListQuery((prev) => {
      const next: Record<string, string> = { ...prev, receipts_page: "1" }
      const apply = (key: string, val: string, omitWhen: string[] = [""]) => {
        const v = val.trim()
        if (omitWhen.includes(v)) {
          delete next[key]
        } else {
          next[key] = v
        }
      }
      if ("q" in patch) apply("receipts_q", patch.q ?? "")
      if ("status" in patch) apply("receipts_status", patch.status ?? "all", ["", "all"])
      if ("sort" in patch) apply("receipts_sort", patch.sort ?? "created_desc", ["", "created_desc"])
      if ("dateFrom" in patch) apply("receipts_date_from", patch.dateFrom ?? "")
      if ("dateTo" in patch) apply("receipts_date_to", patch.dateTo ?? "")
      if ("amountMin" in patch) apply("receipts_amount_min", patch.amountMin ?? "")
      if ("amountMax" in patch) apply("receipts_amount_max", patch.amountMax ?? "")
      return next
    })
  }, [])
  const [lang, setLang] = useState<"fa" | "en">(boot.lang === "fa" ? "fa" : "en")
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [activeTab, setActiveTab] = useState(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return "home"
    let tab =
      typeof window !== "undefined"
        ? parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).tab
        : parseActiveDashTab(b)
    const legSite = resolveLegacySiteTab(tab)
    if (legSite.subtab && typeof window !== "undefined") {
      writeSiteSubtabToUrl(legSite.subtab)
    }
    const legPlans = resolveLegacyPlansTab(legSite.tab)
    return legPlans.tab
  })
  const [userDetailId, setUserDetailId] = useState<number | null>(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return null
    if (typeof window !== "undefined")
      return parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).userDetailId
    return null
  })
  const [resellerContextId, setResellerContextId] = useState<number | null>(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return null
    if (typeof window !== "undefined")
      return (
        parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).resellerContextId ?? null
      )
    return null
  })

  const dashboardBaseUrl = boot.dashboardUrl || `${window.location.origin}/dashboard/`
  const dashboardLoginUrl = `${dashboardBaseUrl.replace(/\/?$/, "")}/login/`
  const allowedResellerTabs = useMemo(() => {
    if (!isReseller) return new Set<string>()
    const server = data?.resellerAllowedTabs
    if (server && typeof server === "object") {
      const out = new Set<string>()
      for (const [k, v] of Object.entries(server as Record<string, unknown>)) {
        if (v === true) out.add(k)
      }
      return out
    }
    const permsFromData =
      data?.actorPermissions && typeof data.actorPermissions === "object"
        ? (data.actorPermissions as Record<string, boolean>)
        : null
    const permsFromBoot =
      boot.actorPermissions && typeof boot.actorPermissions === "object"
        ? (boot.actorPermissions as Record<string, boolean>)
        : null
    const perms = permsFromData ?? permsFromBoot ?? {}
    const out = new Set<string>()
    for (const [tab, perm] of Object.entries(RESELLER_ALLOWED_BY_PERMISSION)) {
      if (perm == null) {
        out.add(tab)
      } else if (perms[perm] === true) {
        out.add(tab)
      }
    }
    return out
  }, [isReseller, data, boot])
  const safeResellerTab = useCallback(
    (tab: string) => {
      if (!isReseller) return tab
      return allowedResellerTabs.has(tab) ? tab : "dashboard"
    },
    [isReseller, allowedResellerTabs]
  )

  const selectTab = useCallback(
    (key: string) => {
      if (!isOperator) {
        if (key === "home") setActiveTab("home")
        return
      }
      let mapped = key === "general" ? (isReseller ? "dashboard" : "monitoring") : key
      mapped = mapTabForReseller(mapped, isReseller)
      const tabKey = safeResellerTab(mapped)
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      const url = buildDashboardTabUrl(base, tabKey)
      window.history.pushState({ tab: tabKey }, "", url)
      setActiveTab(tabKey)
      setUserDetailId(null)
      setResellerContextId(null)
    },
    [isOperator, dashboardBaseUrl, safeResellerTab, isReseller]
  )

  const openUserDetail = useCallback(
    (id: number) => {
      if (!isOperator || !Number.isFinite(id) || id < 1) return
      if (isReseller && !allowedResellerTabs.has("users")) return
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.pushState({ tab: "users", userDetailId: id }, "", `${base}/users/u/${id}/`)
      setActiveTab("users")
      setUserDetailId(id)
      setResellerContextId(null)
    },
    [isOperator, dashboardBaseUrl, isReseller, allowedResellerTabs]
  )

  const onImpersonateReseller = useCallback(
    async (svpUserId: number) => {
      const restBase = (boot.restUrl || "").replace(/\/$/, "")
      if (!restBase || !boot.nonce || !Number.isFinite(svpUserId) || svpUserId < 1) return
      const r = await fetch(`${restBase}/dashboard/impersonate/start`, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": boot.nonce,
        },
        body: JSON.stringify({ targetSvpUserId: svpUserId }),
      })
      if (r.ok) window.location.reload()
    },
    [boot.restUrl, boot.nonce]
  )

  const isLoggedIn = boot.isLoggedIn !== false

  const redirectToDashboardLogin = useCallback(() => {
    if (!isLoggedIn) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (/\/dashboard\/login$/i.test(path)) return
    window.location.href = dashboardLoginUrl
  }, [dashboardLoginUrl, isLoggedIn])

  const fetchDashState = useCallback(
    (opts?: { refreshPanelHealth?: boolean; refreshLivePanelMetrics?: boolean }) => {
      if (!isLoggedIn) return
      const restBase = (boot.restUrl || "").replace(/\/$/, "")
      if (!restBase) return
      const handleAuthResponse = (r: Response) => {
        if (r.status === 401 || r.status === 403) {
          redirectToDashboardLogin()
          return null
        }
        return r.json()
      }
      if (!isOperator) {
        dashStateAbortRef.current?.abort()
        const ac = new AbortController()
        dashStateAbortRef.current = ac
        const { signal } = ac
        void fetch(`${restBase}/dashboard/me/state`, {
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": boot.nonce || "",
          },
          credentials: "include",
          signal,
        })
          .then(handleAuthResponse)
          .then((json) => {
            if (signal.aborted || json === null) return
            setData(json)
          })
          .catch((err: unknown) => {
            if (signal.aborted) return
            if (err instanceof DOMException && err.name === "AbortError") return
            setData({ ok: false })
          })
        return
      }
      dashStateAbortRef.current?.abort()
      const ac = new AbortController()
      dashStateAbortRef.current = ac
      const { signal } = ac
      const tab = activeTab
      const feat = (data?.settings as Record<string, unknown> | undefined)?.features
      const l2tpOn: boolean =
        typeof feat === "object" &&
        feat !== null &&
        (feat as Record<string, unknown>).l2tp === true
      const q = buildAdminStateQuery(listQuery, {
        refreshPanelHealth: opts?.refreshPanelHealth,
        refreshLivePanelMetrics: opts?.refreshLivePanelMetrics,
        activeTab: tab,
        resellerOperator: isReseller,
        l2tpEnabled: l2tpOn,
        includePlansForUserDetail:
          tab === "users" && userDetailId != null && userDetailId > 0,
      })
      const applyJson = (json: unknown) => {
        if (signal.aborted || json === null) return
        setData(json as DashData)
      }
      const onFetchError = (err: unknown) => {
        if (signal.aborted) return
        if (err instanceof DOMException && err.name === "AbortError") return
        setData({ ok: false })
      }
      if (isAdmin && resellerContextId && resellerContextId > 0) {
        const sep = q.includes("?") ? "&" : "?"
        const q2 = `${q}${sep}resellerContextId=${encodeURIComponent(String(resellerContextId))}`
        void fetch(`${restBase}/dashboard/admin/state${q2}`, {
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": boot.nonce || "",
          },
          credentials: "include",
          signal,
        })
          .then(handleAuthResponse)
          .then(applyJson)
          .catch(onFetchError)
        return
      }
      void fetch(`${restBase}/dashboard/admin/state${q}`, {
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": boot.nonce || "",
        },
        credentials: "include",
        signal,
      })
        .then(handleAuthResponse)
        .then(applyJson)
        .catch(onFetchError)
    },
    [
      boot,
      isLoggedIn,
      isOperator,
      isAdmin,
      isReseller,
      listQuery,
      activeTab,
      resellerContextId,
      userDetailId,
      redirectToDashboardLogin,
    ]
  )

  useEffect(() => {
    if (!isLoggedIn) return
    fetchDashState()
  }, [isLoggedIn, fetchDashState])

  useEffect(() => {
    if (!isOperator) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (/\/dashboard\/dashboard$/i.test(path)) {
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.replaceState({ tab: "dashboard" }, "", buildDashboardTabUrl(base, "dashboard"))
      setActiveTab("dashboard")
    }
  }, [isOperator, dashboardBaseUrl])

  useEffect(() => {
    if (!isOperator) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (path.endsWith("/dashboard/general") || /\/dashboard\/general$/i.test(path)) {
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      const tab = isReseller ? "dashboard" : "monitoring"
      window.history.replaceState({ tab }, "", buildDashboardTabUrl(base, tab))
      setActiveTab(tab)
    }
  }, [isOperator, dashboardBaseUrl, isReseller])

  useEffect(() => {
    if (!isOperator) return
    const legSite = resolveLegacySiteTab(activeTab)
    const legPlans = resolveLegacyPlansTab(legSite.tab)
    const nextTab = legPlans.tab
    if (nextTab === activeTab && !legSite.subtab) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    if (legSite.subtab) writeSiteSubtabToUrl(legSite.subtab)
    let url: string
    if (legSite.subtab != null) {
      url = `${base}/site_settings/?site_subtab=${encodeURIComponent(legSite.subtab)}`
    } else {
      url = buildDashboardTabUrl(base, nextTab)
    }
    window.history.replaceState({ tab: nextTab }, "", url)
    setActiveTab(nextTab)
  }, [isOperator, activeTab, dashboardBaseUrl])

  useEffect(() => {
    if (!isReseller) return
    const safeTab = safeResellerTab(activeTab)
    if (safeTab === activeTab) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    window.history.replaceState({ tab: safeTab }, "", buildDashboardTabUrl(base, safeTab))
    setActiveTab(safeTab)
    setUserDetailId(null)
  }, [isReseller, activeTab, safeResellerTab, dashboardBaseUrl])

  useEffect(() => {
    if (!isReseller || activeTab !== "reseller_workspace") return
    const id = resellerContextId
    if (id == null || id < 1) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    window.history.replaceState({ tab: "users", userDetailId: id }, "", `${base}/users/u/${id}/`)
    setActiveTab("users")
    setUserDetailId(id)
    setResellerContextId(null)
  }, [isReseller, activeTab, resellerContextId, dashboardBaseUrl])

  /** `/dashboard/reseller_workspace/` without numeric id falls through to unknown tab — redirect. */
  useEffect(() => {
    if (!isOperator || activeTab !== "reseller_workspace") return
    if (resellerContextId != null && resellerContextId > 0) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    const fallback = isAdmin ? "resellers" : "dashboard"
    window.history.replaceState({ tab: fallback }, "", buildDashboardTabUrl(base, fallback))
    setActiveTab(fallback)
    setResellerContextId(null)
  }, [isOperator, isAdmin, activeTab, resellerContextId, dashboardBaseUrl])

  useEffect(() => {
    const pollHere =
      activeTab === "dashboard" || activeTab === "monitoring"
    if (!isOperator || !pollHere) return
    const ms = 25000
    const id = window.setInterval(() => {
      if (document.visibilityState === "hidden") return
      fetchDashState()
    }, ms)
    const onVis = () => {
      if (document.visibilityState === "visible") fetchDashState()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => {
      window.clearInterval(id)
      document.removeEventListener("visibilitychange", onVis)
    }
  }, [isOperator, isReseller, activeTab, fetchDashState])

  useEffect(() => {
    const isFa = lang === "fa"
    void i18n.changeLanguage(lang)
    document.documentElement.lang = isFa ? "fa" : "en"
    document.documentElement.dir = isFa ? "rtl" : "ltr"
    document.body.dir = isFa ? "rtl" : "ltr"
  }, [i18n, lang])

  useEffect(() => {
    const onFsChange = () => setIsFullscreen(Boolean(document.fullscreenElement))
    document.addEventListener("fullscreenchange", onFsChange)
    return () => document.removeEventListener("fullscreenchange", onFsChange)
  }, [])

  const toggleFullscreen = useCallback(async () => {
    try {
      if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen()
      } else {
        await document.exitFullscreen()
      }
    } catch {
      /* ignore */
    }
  }, [])

  useEffect(() => {
    const onPop = () => {
      if (!isOperator) {
        setActiveTab("home")
        return
      }
      const loc = parseDashFromPath(window.location.pathname, { reseller: isReseller })
      const legSite = resolveLegacySiteTab(loc.tab)
      const legPlans = resolveLegacyPlansTab(legSite.tab)
      if (legSite.subtab) writeSiteSubtabToUrl(legSite.subtab)
      setActiveTab(safeResellerTab(legPlans.tab))
      setUserDetailId(loc.userDetailId)
      setResellerContextId(loc.resellerContextId ?? null)
    }
    window.addEventListener("popstate", onPop)
    return () => window.removeEventListener("popstate", onPop)
  }, [isOperator, safeResellerTab, isReseller])

  const isFa = lang === "fa"
  const sidebarSide: "left" | "right" = isFa ? "right" : "left"
  const navTabs: NavTab[] = useMemo(
    () => (isOperator ? [] : [{ key: "home", label: t("layout.breadcrumbHome") }]),
    [isOperator, t],
  )
  const operatorNavSections: AdminNavSection[] | undefined = useMemo(() => {
    if (!isReseller) return undefined
    return filterAdminNavForReseller(ADMIN_NAV_SECTIONS, allowedResellerTabs)
  }, [isReseller, allowedResellerTabs])

  const currentSectionLabel = useMemo(() => {
    if (!isOperator) {
      return t("layout.breadcrumbHome")
    }
    if (activeTab === "users" && userDetailId != null && userDetailId > 0) {
      return t("layout.userDetailTitle", { id: userDetailId })
    }
    return t(`sidebar.items.${activeTab}`, { defaultValue: activeTab })
  }, [isOperator, activeTab, userDetailId, t])

  const sidebarProfile = useMemo(() => {
    const d = data?.user as
      | { label?: string; tg_user_id?: unknown; bale_user_id?: unknown }
      | undefined
    const b = boot.user as typeof d
    const tg = Number(d?.tg_user_id ?? b?.tg_user_id ?? 0) || 0
    const bl = Number(d?.bale_user_id ?? b?.bale_user_id ?? 0) || 0
    const labelRaw = String(d?.label ?? b?.label ?? "").trim()
    return { label: labelRaw, tg_user_id: tg, bale_user_id: bl }
  }, [data?.user, boot])

  const user = {
    name:
      sidebarProfile.label ||
      `#${formatNumber(boot.svpUserId || 0, isFa)}`,
    tgUserId: sidebarProfile.tg_user_id,
    baleUserId: sidebarProfile.bale_user_id,
    avatar: "",
    logoutUrl: boot.logoutUrl || dashboardBaseUrl,
  }
  const impersonating = Boolean(boot.impersonating)
  const impersonationTargetLabel = String(boot.impersonationTargetLabel ?? "")
  const langLabel = isFa ? t("layout.langSwitchToEn") : t("layout.langSwitchToFa")
  const effectiveActiveTab = isAdmin || isReseller ? activeTab : "home"

  const botLinks = useMemo(() => {
    const overview = data?.overview as { bot?: { telegram_bot_username?: string; bale_bot_username?: string } } | undefined
    const bot = overview?.bot
    return {
      telegram: botPlatformUrl("telegram", String(bot?.telegram_bot_username ?? "")),
      bale: botPlatformUrl("bale", String(bot?.bale_bot_username ?? "")),
    }
  }, [data])

  const sidebarVariant: "admin" | "reseller" | "user" =
    activePersona === "user" ? "user" : activePersona === "reseller" ? "reseller" : "admin"

  const sidebarEl = (
    <AppSidebar
      side={sidebarSide}
      variant={sidebarVariant}
      navTabs={navTabs}
      user={user}
      activeTabKey={effectiveActiveTab}
      onSelectTab={selectTab}
      dashboardBaseUrl={dashboardBaseUrl}
      siteName={String(boot.siteName ?? "")}
      siteIconUrl={boot.siteIconUrl}
      onOpenUserDetail={isOperator ? openUserDetail : undefined}
      userSearchRestUrl={isOperator ? String(boot.restUrl ?? "") : undefined}
      userSearchNonce={isOperator ? String(boot.nonce ?? "") : undefined}
      adminSections={operatorNavSections}
      activePersona={activePersona}
      availablePersonas={availablePersonas}
      personaRestUrl={String(boot.restUrl ?? "")}
      personaNonce={String(boot.nonce ?? "")}
      personaSwitchBlocked={impersonating}
    />
  )

  const insetEl = (
    <SidebarInset className="flex min-h-0 flex-1 flex-col">
      <header
        dir={isFa ? "rtl" : "ltr"}
        className="flex h-16 w-full shrink-0 items-center gap-2 border-b px-4"
      >
        <SidebarTrigger className={cn("-ms-1 shrink-0", isFa && "rotate-180")} />
        <Separator orientation="vertical" className="me-2 h-4 shrink-0" />
        <Breadcrumb className="min-w-0 flex-1" dir={isFa ? "rtl" : undefined}>
          <BreadcrumbList>
            <BreadcrumbItem>{t("dashboard")}</BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>
                {isOperator ? currentSectionLabel : t("myPanel")}
              </BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
        <div className="ms-auto flex shrink-0 items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={t("layout.fullscreen")}
            onClick={() => void toggleFullscreen()}
          >
            {isFullscreen ? <Minimize className="size-4" /> : <Maximize className="size-4" />}
          </Button>
          {botLinks.telegram ? (
            <Button variant="outline" size="icon" asChild>
              <a
                href={botLinks.telegram}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={t("layout.openTelegramBot")}
              >
                <TelegramLogo />
              </a>
            </Button>
          ) : null}
          {botLinks.bale ? (
            <Button variant="outline" size="icon" asChild>
              <a
                href={botLinks.bale}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={t("layout.openBaleBot")}
              >
                <BaleLogo />
              </a>
            </Button>
          ) : null}
          <Button variant="outline" onClick={() => setLang(isFa ? "en" : "fa")}>
            {langLabel}
          </Button>
          <AccentMenu
            initialAccent={boot.uiAccent}
            restUrl={String(boot.restUrl ?? "")}
            nonce={String(boot.nonce ?? "")}
          />
          <Button
            variant="outline"
            size="icon"
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
          >
            {theme === "dark" ? <Sun /> : <Moon />}
          </Button>
        </div>
      </header>
      <div
        dir={isFa ? "rtl" : "ltr"}
        className="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-auto p-4 md:p-6"
      >
        {!data ? (
          <p className="text-sm text-muted-foreground">{t("loading")}</p>
        ) : isOperator ? (
          <DashboardAdminView
            data={data}
            activeTab={effectiveActiveTab}
            userDetailId={userDetailId}
            isReseller={isReseller}
            allowedNavTabs={isReseller ? allowedResellerTabs : null}
            isFa={isFa}
            dashboardBaseUrl={dashboardBaseUrl}
            onSelectTab={selectTab}
            onOpenUserDetail={openUserDetail}
            onCloseUserDetail={() => {
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "users" }, "", `${base}/users/`)
              setUserDetailId(null)
            }}
            onOpenResellerWorkspace={(rid) => {
              const id = Number(rid)
              if (!Number.isFinite(id) || id < 1) return
              if (isReseller) {
                openUserDetail(id)
                return
              }
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "reseller_workspace", resellerContextId: id }, "", `${base}/reseller_workspace/${id}/`)
              setResellerContextId(id)
              setActiveTab("reseller_workspace")
            }}
            setListQuery={setListQuery}
            usersSearchQuery={listQuery.users_q ?? ""}
            onUsersSearchQueryChange={(q) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, users_page: "1" }
                if (q.trim() === "") {
                  delete next.users_q
                } else {
                  next.users_q = q.trim()
                }
                return next
              })
            }}
            resellersSearchQuery={listQuery.users_q ?? ""}
            resellersStatusFilter={listQuery.resellers_status ?? "all"}
            onResellersFiltersChange={(patch) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, resellers_page: "1" }
                if (patch.q !== undefined) {
                  if (patch.q.trim() === "") delete next.users_q
                  else next.users_q = patch.q.trim()
                }
                if (patch.status !== undefined) {
                  if (patch.status === "all") delete next.resellers_status
                  else next.resellers_status = patch.status
                }
                return next
              })
            }}
            receiptsListFilters={receiptsListFilters}
            onReceiptsListFiltersChange={onReceiptsListFiltersChange}
            onRefreshPanelHealth={() => fetchDashState({ refreshPanelHealth: true })}
            onRefreshLivePanelMetrics={() => fetchDashState({ refreshLivePanelMetrics: true })}
            onAdminMutateSuccess={() => fetchDashState()}
            onImpersonateReseller={isAdmin && !impersonating ? onImpersonateReseller : undefined}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            {Number(boot.svpUserId) > 0 ? t("layout.userPortalSoon") : t("noLinkedUser")}
          </p>
        )}
      </div>
    </SidebarInset>
  )

  if (boot.isLoggedIn === false) {
    return <DashboardLogin isFa={boot.lang === "fa"} />
  }

  return (
    <SidebarProvider dir={isFa ? "rtl" : "ltr"} className="flex-col">
      {impersonating && impersonationTargetLabel ? (
        <ImpersonationBanner
          targetLabel={impersonationTargetLabel}
          isFa={isFa}
          restBase={String(boot.restUrl ?? "")}
          nonce={String(boot.nonce ?? "")}
          dashboardBaseUrl={dashboardBaseUrl}
        />
      ) : null}
      <div className="flex min-h-0 w-full flex-1">
        {sidebarEl}
        {insetEl}
      </div>
    </SidebarProvider>
  )
}

export default App

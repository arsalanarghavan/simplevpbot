import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { Moon, Sun, Users } from "lucide-react"
import { useTheme } from "next-themes"
import { AppSidebar } from "@/components/app-sidebar"
import { DashboardAdminView } from "@/components/dashboard-admin-view"
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
import { buildAdminStateQuery } from "@/lib/dash-pagination"
import { parseActiveDashTab, parseDashFromPath } from "@/lib/dash-tab"
import { formatNumber } from "@/lib/format-locale"
import { ADMIN_NAV_SECTIONS, type AdminNavSection } from "@/config/admin-nav"

type DashData = {
  navTabs?: NavTab[]
  user?: { label?: string }
} & Record<string, unknown>
type NavTab = { key: string; label: string }

function userHomeNav(isFa: boolean): NavTab[] {
  return [{ key: "home", label: isFa ? "خانه" : "Home" }]
}

const RESELLER_ALLOWED_BY_PERMISSION: Record<string, string | null> = {
  dashboard: null,
  monitoring: null,
  users: "users.manage",
  resellers: "users.manage",
  plans: "plans.manage",
  plan_cats: "plans.manage",
  reseller_bots: "services.manage",
}

function App() {
  const { t, i18n } = useTranslation()
  const { theme, setTheme } = useTheme()
  const boot = useMemo(() => window.__SIMPLEVPBOT_DASH__ || {}, [])

  const isAdmin = Boolean(boot.isAdmin)
  const isReseller = Boolean(boot.isReseller)
  const isOperator = isAdmin || isReseller
  const [data, setData] = useState<DashData | null>(null)
  /** Query params for GET admin/state list pagination (e.g. users_page). */
  const [listQuery, setListQuery] = useState<Record<string, string>>({})
  const [lang, setLang] = useState<"fa" | "en">(boot.lang === "fa" ? "fa" : "en")
  const [activeTab, setActiveTab] = useState(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return "home"
    if (typeof window !== "undefined") return parseDashFromPath(window.location.pathname).tab
    return parseActiveDashTab(b)
  })
  const [userDetailId, setUserDetailId] = useState<number | null>(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return null
    if (typeof window !== "undefined") return parseDashFromPath(window.location.pathname).userDetailId
    return null
  })

  const dashboardBaseUrl = boot.dashboardUrl || `${window.location.origin}/dashboard/`
  const allowedResellerTabs = useMemo(() => {
    if (!isReseller) return new Set<string>()
    const perms =
      data?.actorPermissions && typeof data.actorPermissions === "object"
        ? (data.actorPermissions as Record<string, boolean>)
        : {}
    const out = new Set<string>()
    for (const [tab, perm] of Object.entries(RESELLER_ALLOWED_BY_PERMISSION)) {
      if (perm == null || perms[perm] !== false) out.add(tab)
    }
    return out
  }, [isReseller, data])
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
      const tabKey = safeResellerTab(key === "general" ? "monitoring" : key)
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      const url = `${base}/${encodeURIComponent(tabKey)}/`
      window.history.pushState({ tab: tabKey }, "", url)
      setActiveTab(tabKey)
      setUserDetailId(null)
    },
    [isOperator, dashboardBaseUrl, safeResellerTab]
  )

  const openUserDetail = useCallback(
    (id: number) => {
      if (!isOperator || !Number.isFinite(id) || id < 1) return
      if (isReseller && !allowedResellerTabs.has("users")) return
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.pushState({ tab: "users", userDetailId: id }, "", `${base}/users/u/${id}/`)
      setActiveTab("users")
      setUserDetailId(id)
    },
    [isOperator, dashboardBaseUrl, isReseller, allowedResellerTabs]
  )

  const fetchDashState = useCallback(
    (opts?: { refreshPanelHealth?: boolean; refreshLivePanelMetrics?: boolean }) => {
      const restBase = (boot.restUrl || "").replace(/\/$/, "")
      if (!restBase) return
      if (!isOperator) {
        void fetch(`${restBase}/dashboard/me/state`, {
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": boot.nonce || "",
          },
          credentials: "include",
        })
          .then((r) => r.json())
          .then((json) => setData(json))
          .catch(() => setData({ ok: false }))
        return
      }
      const tab = activeTab
      const q = buildAdminStateQuery(listQuery, {
        refreshPanelHealth: opts?.refreshPanelHealth,
        refreshLivePanelMetrics: opts?.refreshLivePanelMetrics,
        activeTab: tab,
      })
      void fetch(`${restBase}/dashboard/admin/state${q}`, {
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": boot.nonce || "",
        },
        credentials: "include",
      })
        .then((r) => r.json())
        .then((json) => setData(json))
        .catch(() => setData({ ok: false }))
    },
    [boot, isOperator, listQuery, activeTab]
  )

  useEffect(() => {
    if (!isOperator) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (path.endsWith("/dashboard/general") || /\/dashboard\/general$/i.test(path)) {
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.replaceState({ tab: "monitoring" }, "", `${base}/monitoring/`)
      setActiveTab("monitoring")
    }
  }, [isOperator, dashboardBaseUrl])

  useEffect(() => {
    fetchDashState()
  }, [fetchDashState])

  useEffect(() => {
    if (!isReseller) return
    const safeTab = safeResellerTab(activeTab)
    if (safeTab === activeTab) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    window.history.replaceState({ tab: safeTab }, "", `${base}/${encodeURIComponent(safeTab)}/`)
    setActiveTab(safeTab)
    setUserDetailId(null)
  }, [isReseller, activeTab, safeResellerTab, dashboardBaseUrl])

  useEffect(() => {
    if (!isOperator || (activeTab !== "dashboard" && activeTab !== "monitoring")) return
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
  }, [isOperator, activeTab, fetchDashState])

  useEffect(() => {
    const isFa = lang === "fa"
    void i18n.changeLanguage(lang)
    document.documentElement.lang = isFa ? "fa" : "en"
    document.documentElement.dir = isFa ? "rtl" : "ltr"
    document.body.dir = isFa ? "rtl" : "ltr"
  }, [i18n, lang])

  useEffect(() => {
    const onPop = () => {
      if (!isOperator) {
        setActiveTab("home")
        return
      }
      const loc = parseDashFromPath(window.location.pathname)
      setActiveTab(safeResellerTab(loc.tab))
      setUserDetailId(loc.userDetailId)
    }
    window.addEventListener("popstate", onPop)
    return () => window.removeEventListener("popstate", onPop)
  }, [isOperator, safeResellerTab])

  const isFa = lang === "fa"
  const sidebarSide: "left" | "right" = isFa ? "right" : "left"
  const navTabs: NavTab[] = isOperator ? [] : userHomeNav(isFa)
  const resellerSections: AdminNavSection[] = useMemo(() => {
    if (!isReseller) return ADMIN_NAV_SECTIONS
    const overview = ADMIN_NAV_SECTIONS.find((s) => s.id === "overview")
    if (!overview) return ADMIN_NAV_SECTIONS
    return [
      overview,
      {
        id: "users",
        hintKey: "sidebar.sections.users",
        entries: [
          {
            kind: "collapsible",
            id: "reseller_workspace",
            icon: Users,
            labelKey: "sidebar.groups.resellerWorkspace",
            children: [
              { tabKey: "users" },
              { tabKey: "resellers" },
              { tabKey: "plans" },
              { tabKey: "plan_cats" },
              { tabKey: "reseller_bots" },
            ],
          },
        ],
      },
    ]
  }, [isReseller])

  const currentSectionLabel = useMemo(() => {
    if (!isOperator) {
      return userHomeNav(isFa)[0].label
    }
    if (activeTab === "users" && userDetailId != null && userDetailId > 0) {
      return isFa ? `کاربر #${userDetailId}` : `User #${userDetailId}`
    }
    return t(`sidebar.items.${activeTab}`, { defaultValue: activeTab })
  }, [isOperator, isFa, activeTab, userDetailId, t])

  const user = {
    name: data?.user?.label || `#${formatNumber(boot.svpUserId || 0, isFa)}`,
    email: isAdmin ? "admin@dashboard" : "user@dashboard",
    avatar: "",
    logoutUrl: boot.logoutUrl || "/wp-login.php?action=logout",
  }
  const langLabel = isFa ? "فارسی 🇮🇷" : "English 🇺🇸"
  const effectiveActiveTab = isAdmin || isReseller ? activeTab : "home"

  const sidebarEl = (
    <AppSidebar
      side={sidebarSide}
      variant={isAdmin ? "admin" : isReseller ? "reseller" : "user"}
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
      adminSections={resellerSections}
    />
  )

  const insetEl = (
    <SidebarInset className="flex min-h-0 flex-1 flex-col">
      <header
        dir={isFa ? "rtl" : "ltr"}
        className="flex h-16 w-full shrink-0 items-center gap-2 border-b px-4"
      >
        {!isFa ? (
          <>
            <SidebarTrigger className="-ms-1 shrink-0" />
            <Separator orientation="vertical" className="me-2 h-4 shrink-0" />
          </>
        ) : (
          <div className="flex shrink-0 items-center gap-2">
            <Button variant="outline" onClick={() => setLang(isFa ? "en" : "fa")}>
              {langLabel}
            </Button>
            <Button
              variant="outline"
              size="icon"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
            >
              {theme === "dark" ? <Sun /> : <Moon />}
            </Button>
          </div>
        )}
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
        {!isFa ? (
          <div className="ms-auto flex shrink-0 items-center gap-2">
            <Button variant="outline" onClick={() => setLang(isFa ? "en" : "fa")}>
              {langLabel}
            </Button>
            <Button
              variant="outline"
              size="icon"
              onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
            >
              {theme === "dark" ? <Sun /> : <Moon />}
            </Button>
          </div>
        ) : (
          <div className="flex shrink-0 items-center gap-2">
            <Separator orientation="vertical" className="h-4 shrink-0" />
            <SidebarTrigger className="-me-1 shrink-0 rotate-180" />
          </div>
        )}
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
            isFa={isFa}
            dashboardBaseUrl={dashboardBaseUrl}
            onSelectTab={selectTab}
            onOpenUserDetail={openUserDetail}
            onCloseUserDetail={() => {
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "users" }, "", `${base}/users/`)
              setUserDetailId(null)
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
            onRefreshPanelHealth={() => fetchDashState({ refreshPanelHealth: true })}
            onRefreshLivePanelMetrics={() => fetchDashState({ refreshLivePanelMetrics: true })}
            onAdminMutateSuccess={() => fetchDashState()}
          />
        ) : (
          <p className="text-sm text-muted-foreground">
            {Number(boot.svpUserId) > 0
              ? isFa
                ? "پنل شما در همین بخش در آینده تکمیل می‌شود."
                : "Your portal content will be expanded here."
              : t("noLinkedUser")}
          </p>
        )}
      </div>
    </SidebarInset>
  )

  if (boot.isLoggedIn === false) {
    return <DashboardLogin isFa={boot.lang === "fa"} />
  }

  return (
    <SidebarProvider dir={isFa ? "rtl" : "ltr"}>
      {isFa ? (
        <>
          {insetEl}
          {sidebarEl}
        </>
      ) : (
        <>
          {sidebarEl}
          {insetEl}
        </>
      )}
    </SidebarProvider>
  )
}

export default App

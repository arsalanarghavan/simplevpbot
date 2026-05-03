"use client"

import { useMemo } from "react"
import { useTranslation } from "react-i18next"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataPagination } from "@/components/data-pagination"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import {
  formatBytes,
  formatChartDayLabel,
  formatChartTooltipDate,
  formatDateOnly,
  formatDateTime,
  formatNumber,
  formatNumericString,
} from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import type { PaginationMeta } from "@/lib/dash-pagination"

type OverviewUsers = {
  users_approved?: number
  users_pending?: number
  users_rejected?: number
  users_blocked?: number
  users_total?: number
  users_with_telegram?: number
  users_with_bale?: number
  users_today?: number
  services_total?: number
  services_l2tp?: number
}

type StatsPanelLine = {
  panel_id: number
  label: string
  xray_active: number
  xray_inactive: number
  max_online_day: number
}

type StatsPayload = {
  stat_date?: string
  day_offset?: number
  users?: OverviewUsers
  panels?: StatsPanelLine[]
  l2tp_services?: number
}

type PanelHealth = {
  panelId: number
  ok: boolean
  httpStatus: number
  latencyMs: number | null
  checkedAt: string
  error?: string
}

type HostMetrics = {
  loadAvg?: [number, number, number] | null
  memoryBytes?: number | null
  memoryLimitBytes?: number | null
  diskFreeBytes?: number | null
  diskTotalBytes?: number | null
  checkedAt?: string
}

type OnlineDailyPoint = {
  date: string
  totalMaxOnline: number
}

type OverviewPayload = {
  stats?: StatsPayload
  counts?: Record<string, unknown>
  bot?: {
    enabled?: boolean
    telegram_bot_username?: string
    bale_bot_username?: string
  }
  panelHealth?: PanelHealth[]
  host?: HostMetrics
  onlineDailySeries?: OnlineDailyPoint[]
  livePanelSnapshots?: unknown[]
  externalHostSnapshots?: unknown[]
}

export type { OverviewPayload, PanelHealth, StatsPayload }

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function clampPct(p: number): number {
  if (!Number.isFinite(p)) return 0
  return Math.min(100, Math.max(0, p))
}

function truncateUrl(url: string, max = 36): string {
  const u = url.trim()
  if (!u) return "—"
  if (u.length <= max) return u
  return `${u.slice(0, max - 1)}…`
}

function StatCard({
  title,
  value,
  sub,
  isFa,
}: {
  title: string
  value: number
  sub?: string
  isFa: boolean
}) {
  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-card p-4 shadow-sm",
        isFa && "text-right"
      )}
    >
      <p className="text-xs font-medium text-muted-foreground">{title}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">
        {formatNumber(value, isFa)}
      </p>
      {sub ? <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p> : null}
    </div>
  )
}

function QuickLink({
  tabKey,
  label,
  base,
  onSelectTab,
}: {
  tabKey: string
  label: string
  base: string
  onSelectTab: (k: string) => void
}) {
  const root = base.replace(/\/?$/, "")
  const href = `${root}/${encodeURIComponent(tabKey)}/`
  return (
    <Button variant="outline" size="sm" className="h-8" asChild>
      <a
        href={href}
        onClick={(e) => {
          e.preventDefault()
          onSelectTab(tabKey)
        }}
      >
        {label}
      </a>
    </Button>
  )
}

export function DashboardOverview({
  overview,
  panels,
  panelsPagination,
  isFa,
  dashboardBaseUrl,
  onSelectTab,
  onRefreshPanelHealth,
  onPanelsPageChange,
  onPanelsPerPageChange,
}: {
  overview: OverviewPayload | undefined
  panels: DashRecord[]
  panelsPagination: PaginationMeta | null
  isFa: boolean
  dashboardBaseUrl: string
  onSelectTab: (tabKey: string) => void
  onRefreshPanelHealth?: () => void
  onPanelsPageChange: (page: number) => void
  onPanelsPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const u = overview?.stats?.users ?? {}
  const counts = overview?.counts ?? {}
  const bot = overview?.bot ?? {}
  const host = overview?.host
  const series = overview?.onlineDailySeries ?? []

  const healthById = useMemo(() => {
    const m = new Map<number, PanelHealth>()
    for (const h of overview?.panelHealth ?? []) {
      m.set(h.panelId, h)
    }
    return m
  }, [overview?.panelHealth])

  const statsByPanelId = useMemo(() => {
    const m = new Map<number, StatsPanelLine>()
    for (const row of overview?.stats?.panels ?? []) {
      m.set(Number(row.panel_id), row)
    }
    return m
  }, [overview?.stats?.panels])

  const rows = useMemo(() => {
    return (panels || [])
      .filter((p): p is DashRecord => p != null && typeof p === "object")
      .map((p) => {
        const id = num(p.id)
        const st = statsByPanelId.get(id)
        const h = healthById.get(id)
        return { p, id, st, h }
      })
  }, [panels, statsByPanelId, healthById])

  const receiptByStatus = counts.receiptsByStatus as Record<string, number> | undefined

  const memPct = useMemo(() => {
    const used = host?.memoryBytes
    const lim = host?.memoryLimitBytes
    if (used == null || lim == null || lim <= 0) return null
    return clampPct((used / lim) * 100)
  }, [host?.memoryBytes, host?.memoryLimitBytes])

  const diskPct = useMemo(() => {
    const free = host?.diskFreeBytes
    const total = host?.diskTotalBytes
    if (free == null || total == null || total <= 0) return null
    const used = total - free
    return clampPct((used / total) * 100)
  }, [host?.diskFreeBytes, host?.diskTotalBytes])

  const chartRows = useMemo(
    () =>
      series.map((pt) => ({
        ...pt,
        label: pt.date.length >= 10 ? formatChartDayLabel(pt.date, isFa) : formatNumericString(pt.date, isFa),
        totalMaxOnline: num(pt.totalMaxOnline),
      })),
    [series, isFa]
  )

  const loadLine =
    host?.loadAvg && host.loadAvg.length >= 3
      ? host.loadAvg.map((x) => formatNumber(x, isFa)).join(" / ")
      : "—"

  return (
    <div className={cn("space-y-8", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
      <div>
        <h2 className="text-lg font-semibold">{t("dashboardOverview.title")}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t("dashboardOverview.subtitle")}</p>
        {overview?.stats?.stat_date ? (
          <p className="mt-1 text-xs text-muted-foreground">
            {t("dashboardOverview.statDate")}: {formatDateOnly(String(overview.stats.stat_date), isFa)}
          </p>
        ) : null}
      </div>

      <Card className="border-primary/20">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">{t("dashboardOverview.hostThisServer")}</CardTitle>
          <CardDescription>{t("dashboardOverview.hostLoad")}: {loadLine}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>{t("dashboardOverview.hostMem")}</span>
                <span className="tabular-nums">
                  {host?.memoryBytes != null && host?.memoryLimitBytes != null
                    ? `${formatBytes(host.memoryBytes, isFa)} / ${formatBytes(host.memoryLimitBytes, isFa)}`
                    : "—"}
                </span>
              </div>
              <Progress value={memPct ?? 0} className={memPct == null ? "opacity-40" : ""} />
            </div>
            <div className="space-y-2">
              <div className="flex justify-between text-xs text-muted-foreground">
                <span>{t("dashboardOverview.hostDisk")}</span>
                <span className="tabular-nums">
                  {host?.diskFreeBytes != null && host?.diskTotalBytes != null
                    ? `${t("dashboardOverview.diskFreeLabel")}: ${formatBytes(host.diskFreeBytes, isFa)} · ${formatBytes(host.diskTotalBytes, isFa)}`
                    : "—"}
                </span>
              </div>
              <Progress value={diskPct ?? 0} className={diskPct == null ? "opacity-40" : ""} />
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("dashboardOverview.chartOnlineTitle")}</CardTitle>
          <CardDescription>{t("dashboardOverview.chartOnlineSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="h-[220px] w-full min-w-0 ps-0 pe-1">
          {chartRows.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartRows} margin={{ top: 8, right: 8, left: isFa ? 8 : 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="fillOnline" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="hsl(262 83% 58%)" stopOpacity={0.35} />
                    <stop offset="95%" stopColor="hsl(262 83% 58%)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <YAxis
                  width={40}
                  tick={{ fontSize: 11 }}
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(v) => formatNumber(Number(v), isFa)}
                />
                <RechartsTooltip
                  content={({ active, payload }) => {
                    if (!active || !payload?.length) return null
                    const row = payload[0]?.payload as OnlineDailyPoint & { label: string }
                    const val = num(row?.totalMaxOnline)
                    return (
                      <div className="rounded-md border bg-popover px-2 py-1.5 text-xs shadow-md">
                        <div className="text-muted-foreground">
                          {row?.date ? formatChartTooltipDate(String(row.date), isFa) : ""}
                        </div>
                        <div className="font-medium tabular-nums">{formatNumber(val, isFa)}</div>
                      </div>
                    )
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="totalMaxOnline"
                  stroke="hsl(262 83% 58%)"
                  fill="url(#fillOnline)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      <section className="space-y-3">
        <h3 className="text-sm font-medium text-foreground">{t("sidebar.sections.users")}</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard title={t("dashboardOverview.usersTotal")} value={num(u.users_total)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersApproved")} value={num(u.users_approved)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersPending")} value={num(u.users_pending)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersToday")} value={num(u.users_today)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersRejected")} value={num(u.users_rejected)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersBlocked")} value={num(u.users_blocked)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersTelegram")} value={num(u.users_with_telegram)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.usersBale")} value={num(u.users_with_bale)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.servicesTotal")} value={num(u.services_total)} isFa={isFa} />
          <StatCard title={t("dashboardOverview.servicesL2tp")} value={num(u.services_l2tp)} isFa={isFa} />
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-3">
        <div className={cn("rounded-lg border border-border bg-card p-4", isFa && "text-right")}>
          <h3 className="text-sm font-medium">{t("dashboardOverview.botCard")}</h3>
          <p className="mt-2 text-sm">
            {bot.enabled ? t("dashboardOverview.botEnabled") : t("dashboardOverview.botDisabled")}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            {t("dashboardOverview.telegram")}: {String(bot.telegram_bot_username || "—")}
          </p>
          <p className="text-xs text-muted-foreground">
            {t("dashboardOverview.bale")}: {String(bot.bale_bot_username || "—")}
          </p>
        </div>
        <div className={cn("rounded-lg border border-border bg-card p-4", isFa && "text-right")}>
          <h3 className="text-sm font-medium">{t("dashboardOverview.financeCard")}</h3>
          <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
            <li>
              {t("dashboardOverview.plansCount")}: {formatNumber(num(counts.plans), isFa)}
            </li>
            <li>
              {t("dashboardOverview.planCategories")}: {formatNumber(num(counts.planCategories), isFa)}
            </li>
            <li>
              {t("dashboardOverview.cardsCount")}: {formatNumber(num(counts.cards), isFa)}
            </li>
            <li>
              {t("dashboardOverview.receiptsSample")}: {formatNumber(num(counts.receiptsSample), isFa)}
            </li>
            <li>
              {t("dashboardOverview.discountCodes")}: {formatNumber(num(counts.discountCodes), isFa)}
            </li>
          </ul>
          {receiptByStatus && Object.keys(receiptByStatus).length > 0 ? (
            <div className="mt-3 border-t border-border pt-2">
              <p className="text-xs font-medium text-foreground">{t("dashboardOverview.receiptsByStatus")}</p>
              <div className="mt-1 flex flex-wrap gap-1">
                {Object.entries(receiptByStatus).map(([k, v]) => (
                  <span
                    key={k}
                    className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs tabular-nums"
                  >
                    {formatNumericString(k, isFa)}: {formatNumber(v, isFa)}
                  </span>
                ))}
              </div>
            </div>
          ) : null}
        </div>
        <div className={cn("rounded-lg border border-border bg-card p-4", isFa && "text-right")}>
          <h3 className="text-sm font-medium">{t("dashboardOverview.infraCard")}</h3>
          <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
            <li>
              {t("dashboardOverview.panelsCount")}: {formatNumber(num(counts.panels), isFa)}
            </li>
            <li>
              {t("dashboardOverview.l2tpServers")}: {formatNumber(num(counts.l2tpServers), isFa)}
            </li>
            <li>
              {t("sidebar.items.broadcast")}: {formatNumber(num(counts.broadcasts), isFa)}
            </li>
          </ul>
        </div>
      </section>

      <section className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h3 className="text-sm font-medium">{t("dashboardOverview.panelCards")}</h3>
          {onRefreshPanelHealth ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRefreshPanelHealth}>
              {t("dashboardOverview.refreshPanelHealth")}
            </Button>
          ) : null}
        </div>
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            rows.map(({ p, id, st, h }) => {
              const label = String(p.label ?? st?.label ?? `#${formatNumericString(String(id), isFa)}`)
              const url = String(p.panel_url ?? "")
              const active = p.active === true || p.active === 1 || p.active === "1"
              const xa = num(st?.xray_active)
              const xi = num(st?.xray_inactive)
              const xTotal = xa + xi
              const xrayPct = xTotal > 0 ? clampPct((xa / xTotal) * 100) : 0

              const urlEmpty = !url.trim()
              const hasHealth = Boolean(h)
              const httpOk = Boolean(h?.ok)
              const problem = urlEmpty || (hasHealth && !httpOk)
              const healthy = hasHealth && httpOk && active && !urlEmpty

              const tone = !hasHealth
                ? "border-amber-500/50 bg-amber-500/[0.06]"
                : healthy
                  ? "border-emerald-600/50 bg-emerald-600/[0.06]"
                  : problem
                    ? "border-destructive/70 bg-destructive/[0.06]"
                    : "border-amber-500/50 bg-amber-500/[0.06]"

              const httpLabel = h ? formatNumericString(String(h.httpStatus || 0), isFa) : t("dashboardOverview.unknown")

              const checkedShort = h?.checkedAt ? formatDateTime(h.checkedAt, isFa) : "—"

              return (
                <Tooltip key={id || label}>
                  <TooltipTrigger asChild>
                    <Card className={cn("transition-shadow hover:shadow-md", tone)}>
                      <CardHeader className="gap-1 pb-2">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                          <CardTitle className="text-base">{label}</CardTitle>
                          <div className="flex flex-wrap gap-1">
                            <Badge variant={httpOk ? "secondary" : "destructive"}>
                              HTTP {httpLabel}
                            </Badge>
                            <Badge variant={active ? "secondary" : "outline"}>
                              {active
                                ? t("dashboardOverview.badgeDbActive")
                                : t("dashboardOverview.badgeDbInactive")}
                            </Badge>
                          </div>
                        </div>
                        <CardDescription className="font-mono text-xs break-all">
                          {truncateUrl(url)}
                        </CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-3 text-sm">
                        <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                          <span>{t("dashboardOverview.colLatency")}</span>
                          <span className="tabular-nums font-medium text-foreground">
                            {h?.latencyMs != null ? formatNumber(h.latencyMs, isFa) : "—"}
                          </span>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                          <span>{t("dashboardOverview.colXrayActive")}</span>
                          <span className="tabular-nums font-medium text-foreground">
                            {formatNumber(xa, isFa)} / {formatNumber(xi, isFa)}
                          </span>
                        </div>
                        <div className="space-y-1">
                          <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{t("dashboardOverview.xrayShare")}</span>
                            <span className="tabular-nums">{formatNumber(Math.round(xrayPct), isFa)}%</span>
                          </div>
                          <Progress value={xTotal > 0 ? xrayPct : 0} className={xTotal === 0 ? "opacity-40" : ""} />
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 border-t border-border pt-2 text-xs text-muted-foreground">
                          <span>{t("dashboardOverview.colMaxOnline")}</span>
                          <span className="tabular-nums font-medium text-foreground">
                            {st?.max_online_day != null ? formatNumber(num(st.max_online_day), isFa) : "—"}
                          </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                          {t("dashboardOverview.lastCheck")}: {checkedShort}
                        </p>
                      </CardContent>
                    </Card>
                  </TooltipTrigger>
                  <TooltipContent
                    side={isFa ? "left" : "right"}
                    className="max-w-xs text-xs leading-relaxed"
                  >
                    <p>{httpOk ? t("dashboardOverview.ttHttpOk") : t("dashboardOverview.ttHttpFail")}</p>
                    <p className="mt-1">
                      {active ? t("dashboardOverview.ttDbActive") : t("dashboardOverview.ttDbInactive")}
                    </p>
                    {h?.error ? <p className="mt-1 text-destructive">{h.error}</p> : null}
                  </TooltipContent>
                </Tooltip>
              )
            })
          )}
        </div>
        <DataPagination
          meta={panelsPagination}
          isFa={isFa}
          onPageChange={onPanelsPageChange}
          onPerPageChange={onPanelsPerPageChange}
        />
      </section>

      <section className={cn("space-y-2", isFa && "text-right")}>
        <h3 className="text-sm font-medium">{t("dashboardOverview.quickLinks")}</h3>
        <div className="flex flex-wrap gap-2">
          <QuickLink tabKey="users" label={t("sidebar.items.users")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="receipts" label={t("sidebar.items.receipts")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="xui_panels" label={t("sidebar.items.xui_panels")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="plans" label={t("sidebar.items.plans")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="l2tp_servers" label={t("sidebar.items.l2tp_servers")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="bots" label={t("sidebar.items.bots")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          <QuickLink tabKey="monitoring" label={t("sidebar.items.monitoring")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
        </div>
      </section>
    </div>
  )
}

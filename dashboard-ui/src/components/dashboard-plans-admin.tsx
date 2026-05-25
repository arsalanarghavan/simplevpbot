"use client"

import {
  BadgeDollarSign,
  Clock,
  EllipsisVerticalIcon,
  HardDrive,
  Package,
  Radio,
  Server,
  Settings2,
  Tag,
  Users,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useState, type ComponentType, type ReactNode } from "react"
import { useTranslation } from "react-i18next"

import { DashboardWholesaleLinesAdmin } from "@/components/dashboard-wholesale-lines-admin"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import {
  Sheet,
  SheetContent,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import {
  type PlansView,
  isPlansView,
  readPlansViewFromUrl,
  writePlansViewToUrl,
} from "@/lib/plans-subview"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelCanSellPlan(p: DashRecord, resellerMode: boolean): boolean {
  if (!resellerMode) return true
  if (p.can_sell_plan === false) return false
  return true
}

function panelLabel(panels: DashRecord[], panelId: number): string {
  const row = panels.find((p) => num(p.id) === panelId)
  return String(row?.label ?? row?.name ?? `#${panelId}`)
}

function formatClientCap(v: unknown, isFa: boolean, unlimited: string): string {
  const n = num(v)
  return n < 1 ? unlimited : formatNumber(n, isFa)
}

function PlanInfoRow({
  icon: Icon,
  label,
  value,
}: {
  icon: ComponentType<{ className?: string }>
  label: string
  value: ReactNode
}) {
  return (
    <div className="flex min-w-0 gap-2 rounded-md border border-border/60 bg-muted/20 p-2 text-xs">
      <Icon className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <div className="min-w-0 flex-1">
        <p className="text-muted-foreground">{label}</p>
        <div className="truncate font-medium text-foreground">{value}</div>
      </div>
    </div>
  )
}

/** Approximate progress toward next tier (0–100) for ladder UI. */
function ladderProgressApprox(ladder: DashRecord | undefined): number {
  if (!ladder) return 0
  const tg = num(ladder.total_gb)
  const tt = num(ladder.total_wholesale_toman)
  const nextId = ladder.next_tier_id
  if (nextId == null || nextId === undefined || num(nextId) < 1) return 100
  let best = 0
  const gbNeedRaw = ladder.gb_to_next_tier
  const toNeedRaw = ladder.toman_to_next_tier
  if (gbNeedRaw != null && Number.isFinite(Number(gbNeedRaw)) && Number(gbNeedRaw) > 0) {
    const need = Number(gbNeedRaw)
    const target = tg + need
    best = Math.max(best, target > 0 ? Math.min(100, (tg / target) * 100) : 0)
  }
  if (toNeedRaw != null && Number.isFinite(Number(toNeedRaw)) && Number(toNeedRaw) > 0) {
    const need = Number(toNeedRaw)
    const target = tt + need
    best = Math.max(best, target > 0 ? Math.min(100, (tt / target) * 100) : 0)
  }
  return best > 0 ? best : 0
}

type PlanFormState = {
  plan_id: number
  name: string
  category: string
  plan_panel_id: number
  wholesale_line_id: number
  traffic_gb: number
  price: number
  plan_pricing_type: "fixed" | "per_gb"
  price_per_gb: number
  traffic_gb_min: number
  traffic_gb_max: number
  duration_days: number
  clients_count: number
  inbound_id: number
  service_type: "xray" | "l2tp"
  l2tp_server_id: number
  sort_order: number
  plan_active: boolean
}

function emptyForm(defaultPanelId: number, defaultCategory: string, wholesaleLineId = 0): PlanFormState {
  return {
    plan_id: 0,
    name: "",
    category: defaultCategory,
    plan_panel_id: defaultPanelId,
    wholesale_line_id: wholesaleLineId,
    traffic_gb: 0,
    price: 0,
    plan_pricing_type: "fixed",
    price_per_gb: 0,
    traffic_gb_min: 1,
    traffic_gb_max: 100,
    duration_days: 30,
    clients_count: 1,
    inbound_id: 0,
    service_type: "xray",
    l2tp_server_id: 0,
    sort_order: 0,
    plan_active: true,
  }
}

function formFromPlan(p: DashRecord): PlanFormState {
  return {
    plan_id: num(p.id),
    name: String(p.name ?? ""),
    category: String(p.category ?? ""),
    plan_panel_id: num(p.panel_id) || 1,
    wholesale_line_id: num(p.wholesale_line_id),
    traffic_gb: num(p.traffic_gb),
    price: num(p.price),
    plan_pricing_type: p.pricing_type === "per_gb" ? "per_gb" : "fixed",
    price_per_gb: num(p.price_per_gb),
    traffic_gb_min: num(p.traffic_gb_min),
    traffic_gb_max: num(p.traffic_gb_max),
    duration_days: num(p.duration_days),
    clients_count: Math.max(0, num(p.clients_count)),
    inbound_id: num(p.inbound_id),
    service_type: p.service_type === "l2tp" ? "l2tp" : "xray",
    l2tp_server_id: num(p.l2tp_server_id),
    sort_order: num(p.sort_order),
    plan_active: p.active === true || p.active === 1 || p.active === "1",
  }
}

function formToPayload(f: PlanFormState): Record<string, unknown> {
  return {
    name: f.name,
    category: f.category,
    plan_panel_id: f.plan_panel_id,
    wholesale_line_id: f.wholesale_line_id > 0 ? f.wholesale_line_id : 0,
    traffic_gb: f.traffic_gb,
    price: f.price,
    plan_pricing_type: f.plan_pricing_type,
    price_per_gb: f.price_per_gb,
    traffic_gb_min: f.traffic_gb_min,
    traffic_gb_max: f.traffic_gb_max,
    duration_days: f.duration_days,
    clients_count: f.clients_count,
    inbound_id: f.inbound_id,
    service_type: "xray",
    l2tp_server_id: 0,
    sort_order: f.sort_order,
    plan_active: f.plan_active ? 1 : 0,
  }
}

function validateForm(f: PlanFormState, resellerMode: boolean, requireWholesaleLine: boolean): string | null {
  if (!f.name.trim()) return "name"
  if (!f.category.trim()) return "category"
  if (resellerMode && requireWholesaleLine && f.wholesale_line_id < 1) return "wholesale_line"
  if (!resellerMode && f.inbound_id <= 0) return "inbound"
  if (f.plan_pricing_type === "fixed" && f.price <= 0) return "price"
  if (f.plan_pricing_type === "per_gb") {
    if (f.price_per_gb <= 0) return "price_per_gb"
    if (f.traffic_gb_min < 1 || f.traffic_gb_max < 1 || f.traffic_gb_min > f.traffic_gb_max)
      return "traffic_range"
  }
  return null
}

const CLIENT_VALIDATION_LOCALE: Record<string, string> = {
  name: "validationName",
  category: "validationCategory",
  wholesale_line: "validationWholesaleLine",
  inbound: "validationInbound",
  price: "validationPrice",
  price_per_gb: "validationPricePerGb",
  traffic_range: "validationTrafficRange",
}

const SERVER_ERROR_LOCALE: Record<string, string> = {
  invalid: "errorCode_invalid",
  invalid_update: "errorCode_invalid",
  panel_not_allowed: "errorCode_panel_not_allowed",
  wholesale_line_not_assigned: "errorCode_wholesale_line_not_assigned",
  wholesale_line_invalid: "errorCode_wholesale_line_invalid",
  wholesale_line_no_tiers: "errorCode_wholesale_line_no_tiers",
  wholesale_line_bad: "errorCode_wholesale_line_bad",
  below_reseller_floor: "errorCode_below_reseller_floor",
  forbidden: "errorCode_forbidden",
  bad_actor: "errorCode_bad_actor",
  l2tp_forbidden_for_reseller: "errorCode_l2tp_forbidden_for_reseller",
  module_missing: "errorCode_module_missing",
}

function formatPlanMutateError(
  code: string | undefined,
  message: string | undefined,
  tp: (k: string) => string
): string {
  const c = String(code ?? "").trim()
  if (c && SERVER_ERROR_LOCALE[c]) return tp(SERVER_ERROR_LOCALE[c])
  if (c === "invalid" || c === "invalid_update") return tp("errorCode_invalid")
  const msg = String(message ?? c).trim()
  return msg ? `${tp("mutateError")}: ${msg}` : tp("mutateError")
}

const selectClass =
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

/** Mirrors REST `resellerPanelAccessDiagnostics` when the reseller has zero visible panels. */
export type ResellerPanelAccessDiagnostics = {
  stored_rows?: number
  joinable_rows?: number
  orphan_panel_ids?: number[]
  inactive_row_count?: number
}

export function DashboardPlansAdmin({
  plans,
  panels,
  planCategories,
  l2tpServers = [],
  wholesaleLines = [],
  wholesaleLinesCatalog = [],
  initialPlansView = "plans",
  resellerPlanFloors = [],
  resellerMode = false,
  actorSvpUserId = 0,
  panelAccessDiagnostics = null,
  pagination,
  settings,
  showCatalogDefaultsSave = true,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  plans: DashRecord[]
  panels: DashRecord[]
  planCategories: DashRecord[]
  l2tpServers: DashRecord[]
  /** Site admin: full wholesale catalog CRUD (embedded under Plans). */
  wholesaleLinesCatalog?: DashRecord[]
  /** Open wholesale sub-view when landing from legacy URL. */
  initialPlansView?: PlansView
  /** Reseller dashboard: assigned catalog lines with ladder snapshots. */
  wholesaleLines?: DashRecord[]
  /** Per-panel wholesale floor + admin connection presets (reseller dashboard only). */
  resellerPlanFloors?: DashRecord[]
  resellerMode?: boolean
  /** Current actor `svp_users.id` (sidebar) for reseller troubleshooting copy. */
  actorSvpUserId?: number
  panelAccessDiagnostics?: ResellerPanelAccessDiagnostics | null
  pagination: PaginationMeta | null
  settings?: DashRecord
  /** Resellers cannot mutate global catalog defaults (`plans_catalog` / settings_tab). */
  showCatalogDefaultsSave?: boolean
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`plansAdmin.${k}`, opts)

  const hasWholesaleCatalog = resellerMode && wholesaleLines.length > 0
  const [plansView, setPlansView] = useState<PlansView>(initialPlansView)
  const [panelFilter, setPanelFilter] = useState<string>("all")

  useEffect(() => {
    if (resellerMode) return
    setPlansView(readPlansViewFromUrl())
  }, [resellerMode])
  const [lineFilter, setLineFilter] = useState<string>("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<PlanFormState>(() =>
    emptyForm(num(panels[0]?.id) || 1, "")
  )
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const catalogInitial = useMemo(() => {
    const s = settings ?? {}
    return {
      default_concurrent_users: String(Math.max(0, Math.trunc(num(s.default_concurrent_users)) || 2)),
      price_per_extra_user: String(s.price_per_extra_user ?? "0"),
    }
  }, [settings])

  const [catalogForm, setCatalogForm] = useState(catalogInitial)
  useEffect(() => {
    setCatalogForm(catalogInitial)
  }, [catalogInitial])
  const [catalogSaving, setCatalogSaving] = useState(false)
  const [catalogError, setCatalogError] = useState<string | null>(null)
  const [catalogDialogOpen, setCatalogDialogOpen] = useState(false)

  const onSaveCatalogDefaults = useCallback(async () => {
    setCatalogSaving(true)
    setCatalogError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "plans_catalog",
        default_concurrent_users: Math.max(0, Math.trunc(num(catalogForm.default_concurrent_users))),
        price_per_extra_user: catalogForm.price_per_extra_user.trim().replace(",", "."),
      })
      if (!res.ok) {
        setCatalogError(res.message || tp("catalogSaveError"))
        return
      }
      onMutateSuccess?.()
      setCatalogDialogOpen(false)
    } finally {
      setCatalogSaving(false)
    }
  }, [catalogForm, onMutateSuccess, tp])

  const sellablePanels = useMemo(() => {
    if (!resellerMode) return panels
    return panels.filter((p) => panelCanSellPlan(p, true))
  }, [panels, resellerMode])

  const defaultPanelId = useMemo(() => {
    if (panelFilter !== "all") return num(panelFilter)
    const list = resellerMode ? sellablePanels : panels
    return num(list[0]?.id) || num(panels[0]?.id) || 1
  }, [panelFilter, panels, resellerMode, sellablePanels])

  const firstCategoryForPanel = useCallback(
    (pid: number) => {
      const c = planCategories.find((x) => num(x.panel_id) === pid)
      return String(c?.slug ?? "")
    },
    [planCategories]
  )

  const visiblePlans = useMemo(
    () => plans.filter((p) => String(p.service_type ?? "xray") !== "l2tp"),
    [plans]
  )

  const filteredPlans = useMemo(() => {
    if (hasWholesaleCatalog) {
      if (lineFilter === "all") return visiblePlans
      const lid = num(lineFilter)
      return visiblePlans.filter((p) => num(p.wholesale_line_id) === lid)
    }
    if (panelFilter === "all") return visiblePlans
    const pid = num(panelFilter)
    return visiblePlans.filter((p) => num(p.panel_id) === pid)
  }, [visiblePlans, panelFilter, lineFilter, hasWholesaleCatalog])

  const stats = useMemo(() => {
    let active = 0
    let inactive = 0
    for (const p of visiblePlans) {
      const on = p.active === true || p.active === 1 || p.active === "1"
      if (on) active++
      else inactive++
    }
    const total = pagination?.total ?? visiblePlans.length
    return { total, active, inactive }
  }, [visiblePlans, pagination])

  const ranked = useMemo(() => {
    return [...filteredPlans].sort((a, b) => {
      const uc = num(b.userCount) - num(a.userCount)
      if (uc !== 0) return uc
      return String(a.name ?? "").localeCompare(String(b.name ?? ""))
    })
  }, [filteredPlans])

  const floorForPanel = useMemo(() => {
    const pid = form.plan_panel_id
    return resellerPlanFloors.find((x) => num(x.panel_id) === pid)
  }, [form.plan_panel_id, resellerPlanFloors])

  const minPriceFloorPerGb = useMemo(() => {
    if (hasWholesaleCatalog && form.wholesale_line_id > 0) {
      const line = wholesaleLines.find((w) => num(w.id) === form.wholesale_line_id)
      const ladder = line?.ladder as DashRecord | undefined
      const cur = num(ladder?.current_price_per_gb)
      if (cur > 0) return cur
    }
    return num(floorForPanel?.min_price_per_gb_effective)
  }, [hasWholesaleCatalog, form.wholesale_line_id, wholesaleLines, floorForPanel])

  const categoriesForFormPanel = useMemo(
    () => planCategories.filter((c) => num(c.panel_id) === form.plan_panel_id),
    [planCategories, form.plan_panel_id]
  )

  useEffect(() => {
    if (!sheetOpen || !resellerMode) return
    const current = panels.find((x) => num(x.id) === form.plan_panel_id)
    if (current && !panelCanSellPlan(current, true)) {
      const first = sellablePanels[0]
      if (first) {
        const pid = num(first.id)
        setForm((f) => ({
          ...f,
          plan_panel_id: pid,
          category: firstCategoryForPanel(pid) || f.category,
        }))
      }
    }
  }, [sheetOpen, resellerMode, form.plan_panel_id, panels, sellablePanels, firstCategoryForPanel])

  const openAdd = () => {
    setError(null)
    setFormMode("add")
    if (hasWholesaleCatalog) {
      const lid = lineFilter !== "all" ? num(lineFilter) : num(wholesaleLines[0]?.id)
      const line = wholesaleLines.find((w) => num(w.id) === lid) ?? wholesaleLines[0]
      const pid = num(line?.panel_id) || defaultPanelId
      const wlid = num(line?.id) || 0
      setForm(emptyForm(pid, firstCategoryForPanel(pid), wlid))
    } else {
      const pid = defaultPanelId
      setForm(emptyForm(pid, firstCategoryForPanel(pid), 0))
    }
    setSheetOpen(true)
  }

  const openEdit = (p: DashRecord) => {
    setError(null)
    setFormMode("edit")
    setForm(formFromPlan(p))
    setSheetOpen(true)
  }

  const runMutate = async (params: Record<string, unknown>) => {
    setSaving(true)
    setError(null)
    const res = await postAdminMutate("plan", params)
    setSaving(false)
    if (!res.ok) {
      setError(formatPlanMutateError(res.code, res.message, tp))
      return false
    }
    onMutateSuccess?.()
    return true
  }

  const onSaveSheet = async () => {
    const inv = validateForm(form, resellerMode, hasWholesaleCatalog)
    if (inv) {
      const key = CLIENT_VALIDATION_LOCALE[inv]
      setError(key ? tp(key) : tp("mutateInvalid"))
      return
    }
    const payload = formToPayload(form)
    const action = formMode === "add" ? "add" : "update"
    const ok = await runMutate({
      plan_action: action,
      plan_id: formMode === "edit" ? form.plan_id : 0,
      ...payload,
    })
    if (ok) setSheetOpen(false)
  }

  const onToggle = async (p: DashRecord) => {
    await runMutate({ plan_action: "toggle", plan_id: num(p.id) })
  }

  const onConfirmDelete = async () => {
    if (!deleteTarget) return
    const ok = await runMutate({ plan_action: "delete", plan_id: num(deleteTarget.id) })
    if (ok) setDeleteTarget(null)
  }

  return (
    <div className={cn("space-y-6", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
      <div>
        <h2 className="text-xl font-semibold tracking-tight">{tp("title")}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      {resellerMode && panels.length === 0 && wholesaleLines.length === 0 ? (
        <div className="space-y-3">
          <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
            {tp("resellerNoPanels")}
          </p>
          {actorSvpUserId > 0 ? (
            <p className="text-sm text-muted-foreground">{tp("resellerNoPanelsHint", { svpUserId: actorSvpUserId })}</p>
          ) : null}
          {panelAccessDiagnostics ? (
            <div className="space-y-1 rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
              <p className="font-medium text-foreground">{tp("resellerPanelDiagTitle")}</p>
              <p>{tp("resellerPanelDiagStored", { n: panelAccessDiagnostics.stored_rows ?? 0 })}</p>
              <p>{tp("resellerPanelDiagJoinable", { n: panelAccessDiagnostics.joinable_rows ?? 0 })}</p>
              {(panelAccessDiagnostics.orphan_panel_ids?.length ?? 0) > 0 ? (
                <p>
                  {tp("resellerPanelDiagOrphans", {
                    ids: (panelAccessDiagnostics.orphan_panel_ids ?? []).join(", "),
                  })}
                </p>
              ) : null}
              <p>{tp("resellerPanelDiagInactive", { n: panelAccessDiagnostics.inactive_row_count ?? 0 })}</p>
            </div>
          ) : null}
        </div>
      ) : null}

      {!resellerMode ? (
        <Tabs
          value={plansView}
          onValueChange={(v) => {
            const next = isPlansView(v) ? v : "plans"
            setPlansView(next)
            writePlansViewToUrl(next)
          }}
        >
          <TabsList className={cn(isFa && "flex-row-reverse")}>
            <TabsTrigger value="plans">{tp("tabPlans")}</TabsTrigger>
            <TabsTrigger value="wholesale">{tp("tabWholesaleLines")}</TabsTrigger>
          </TabsList>
        </Tabs>
      ) : null}

      {!resellerMode && plansView === "wholesale" ? (
        <DashboardWholesaleLinesAdmin
          catalog={wholesaleLinesCatalog}
          panels={panels}
          l2tpServers={l2tpServers}
          isFa={isFa}
          onMutateSuccess={onMutateSuccess}
        />
      ) : null}

      {resellerMode || plansView === "plans" ? (
        <>
      {hasWholesaleCatalog ? (
        <div className="space-y-3">
          <div>
            <h3 className="text-base font-semibold">{tp("ladderTitle")}</h3>
            <p className="text-sm text-muted-foreground">{tp("ladderSubtitle")}</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {wholesaleLines.map((line) => {
              const lid = num(line.id)
              const ladder = line.ladder as DashRecord | undefined
              const pct = ladderProgressApprox(ladder)
              const curRate = num(ladder?.current_price_per_gb)
              const nextRate = num(ladder?.next_price_per_gb)
              const gbNeed = ladder?.gb_to_next_tier
              const tomanNeed = ladder?.toman_to_next_tier
              const atTop = ladder?.next_tier_id == null || num(ladder?.next_tier_id) < 1
              return (
                <Card key={lid}>
                  <CardHeader className="pb-2">
                    <div className={cn("flex items-center gap-2", isFa && "flex-row-reverse")}>
                      <span
                        className="h-2 w-8 shrink-0 rounded-full"
                        style={{ backgroundColor: String(line.badge_color ?? "#6366f1") }}
                      />
                      <CardTitle className="text-base">{String(line.label ?? `#${lid}`)}</CardTitle>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-3 text-sm">
                    <div className={cn("flex justify-between gap-2", isFa && "flex-row-reverse")}>
                      <span className="text-muted-foreground">{tp("ladderCurrentRate")}</span>
                      <span className="tabular-nums font-semibold">{formatNumber(curRate, isFa)}</span>
                    </div>
                    <div className={cn("flex justify-between gap-2 text-muted-foreground", isFa && "flex-row-reverse")}>
                      <span>{tp("ladderTotalGb")}</span>
                      <span className="tabular-nums">{formatNumber(num(ladder?.total_gb), isFa)}</span>
                    </div>
                    <div className={cn("flex justify-between gap-2 text-muted-foreground", isFa && "flex-row-reverse")}>
                      <span>{tp("ladderTotalToman")}</span>
                      <span className="tabular-nums">{formatNumber(num(ladder?.total_wholesale_toman), isFa)}</span>
                    </div>
                    {!atTop ? (
                      <>
                        <Progress value={pct} className="h-2" />
                        <div className="space-y-1 text-xs text-muted-foreground">
                          <p>
                            {tp("ladderNextTier")}:{" "}
                            <span className="font-medium text-foreground tabular-nums">
                              {formatNumber(nextRate, isFa)}
                            </span>
                          </p>
                          {gbNeed != null && Number(gbNeed) > 0 ? (
                            <p>{tp("ladderGbToGo", { n: formatNumber(Number(gbNeed), isFa) })}</p>
                          ) : null}
                          {tomanNeed != null && Number(tomanNeed) > 0 ? (
                            <p>{tp("ladderTomanToGo", { n: formatNumber(Number(tomanNeed), isFa) })}</p>
                          ) : null}
                        </div>
                      </>
                    ) : (
                      <p className="text-xs text-muted-foreground">{tp("ladderMaxTier")}</p>
                    )}
                  </CardContent>
                </Card>
              )
            })}
          </div>
        </div>
      ) : null}

      {error ? (
        <p className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums text-emerald-600 dark:text-emerald-400">
              {formatNumber(stats.active, isFa)}
            </CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsInactive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums text-muted-foreground">
              {formatNumber(stats.inactive, isFa)}
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      {pagination ? <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p> : null}

      <div className="grid gap-4 xl:grid-cols-[1fr_260px]">
        <div className="min-w-0 space-y-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2">
              <Label htmlFor="catalog-filter" className="sr-only">
                {hasWholesaleCatalog ? tp("filterWholesaleLine") : tp("filterPanel")}
              </Label>
              <span className="text-sm text-muted-foreground">
                {hasWholesaleCatalog ? tp("filterWholesaleLine") : tp("filterPanel")}
              </span>
              <select
                id="catalog-filter"
                className={cn(selectClass, "w-full min-w-[10rem] sm:w-56")}
                value={hasWholesaleCatalog ? lineFilter : panelFilter}
                onChange={(e) =>
                  hasWholesaleCatalog ? setLineFilter(e.target.value) : setPanelFilter(e.target.value)
                }
              >
                <option value="all">{tp("filterAll")}</option>
                {hasWholesaleCatalog
                  ? wholesaleLines.map((w) => (
                      <option key={String(w.id)} value={String(num(w.id))}>
                        {String(w.label ?? w.id)}
                      </option>
                    ))
                  : panels.map((p) => (
                      <option key={String(p.id)} value={String(p.id)}>
                        {String(p.label ?? p.name ?? p.id)}
                      </option>
                    ))}
              </select>
            </div>
            <div className={cn("flex flex-wrap items-center gap-2", isFa && "flex-row-reverse")}>
              {showCatalogDefaultsSave ? (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  onClick={() => setCatalogDialogOpen(true)}
                >
                  <Settings2 className="size-4 shrink-0" aria-hidden />
                  <span>{tp("catalogDefaultsButton")}</span>
                </Button>
              ) : null}
              <Button
                type="button"
                onClick={openAdd}
                disabled={resellerMode && panels.length < 1 && wholesaleLines.length < 1}
              >
                {tp("addPlan")}
              </Button>
            </div>
          </div>

          {filteredPlans.length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center text-sm text-muted-foreground">
                {tp("rankEmpty")}
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
              {filteredPlans.map((p) => {
                const id = num(p.id)
                const pid = num(p.panel_id)
                const wlidPlan = num(p.wholesale_line_id)
                const lineMeta = wholesaleLines.find((w) => num(w.id) === wlidPlan)
                const stripe = lineMeta?.badge_color ? String(lineMeta.badge_color) : undefined
                const uc = num(p.userCount)
                const price = num(p.price)
                const ptype = String(p.pricing_type ?? "fixed")
                const active = p.active === true || p.active === 1 || p.active === "1"
                const priceLabel =
                  ptype === "per_gb"
                    ? `${formatNumber(num(p.price_per_gb), isFa)} / ${tp("gbSuffix")}`
                    : formatNumber(price, isFa)
                return (
                  <Card key={id || String(p.name)} className="relative overflow-hidden pt-0">
                    {stripe ? (
                      <div className="h-1 w-full shrink-0" style={{ backgroundColor: stripe }} />
                    ) : null}
                    <CardHeader className="space-y-2 pb-3 pt-4">
                      <div
                        className={cn(
                          "flex items-start justify-between gap-2",
                          isFa && "flex-row-reverse"
                        )}
                      >
                        <div className="min-w-0 flex-1">
                          <CardTitle className="text-base leading-snug">{String(p.name ?? "—")}</CardTitle>
                          <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                            {tp("cardUsers")}: {formatNumber(uc, isFa)}
                          </p>
                        </div>
                        <div className={cn("flex shrink-0 items-center gap-1", isFa && "flex-row-reverse")}>
                          <Badge variant={active ? "default" : "outline"}>
                            {active ? tp("statsActive") : tp("statsInactive")}
                          </Badge>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon-sm" className="size-8">
                                <EllipsisVerticalIcon className="size-4" />
                                <span className="sr-only">{tp("actions")}</span>
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align={isFa ? "start" : "end"}>
                              <DropdownMenuItem onClick={() => openEdit(p)}>{tp("edit")}</DropdownMenuItem>
                              <DropdownMenuItem onClick={() => void onToggle(p)}>{tp("toggle")}</DropdownMenuItem>
                              <DropdownMenuItem variant="destructive" onClick={() => setDeleteTarget(p)}>
                                {tp("delete")}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent className="grid gap-2 pb-4 sm:grid-cols-2">
                      <PlanInfoRow
                        icon={hasWholesaleCatalog && wlidPlan > 0 ? Package : Server}
                        label={hasWholesaleCatalog && wlidPlan > 0 ? tp("cardWholesaleLine") : tp("cardPanel")}
                        value={
                          hasWholesaleCatalog && wlidPlan > 0
                            ? String(lineMeta?.label ?? "—")
                            : panelLabel(panels, pid)
                        }
                      />
                      <PlanInfoRow icon={Tag} label={tp("cardCategory")} value={String(p.category ?? "—")} />
                      <PlanInfoRow
                        icon={Radio}
                        label={tp("cardInbound")}
                        value={formatNumber(num(p.inbound_id), isFa)}
                      />
                      <PlanInfoRow
                        icon={Clock}
                        label={tp("cardDuration")}
                        value={`${formatNumber(num(p.duration_days), isFa)} ${tp("periodDays")}`}
                      />
                      <PlanInfoRow
                        icon={Users}
                        label={tp("cardClients")}
                        value={formatClientCap(p.clients_count, isFa, tp("clientsUnlimited"))}
                      />
                      <PlanInfoRow icon={BadgeDollarSign} label={tp("cardPrice")} value={priceLabel} />
                      {ptype === "fixed" ? (
                        <PlanInfoRow
                          icon={HardDrive}
                          label={tp("cardTraffic")}
                          value={`${formatNumber(num(p.traffic_gb), isFa)} ${tp("gbSuffix")}`}
                        />
                      ) : (
                        <PlanInfoRow
                          icon={HardDrive}
                          label={tp("cardTrafficRange")}
                          value={`${formatNumber(num(p.traffic_gb_min), isFa)}–${formatNumber(num(p.traffic_gb_max), isFa)} ${tp("gbSuffix")}`}
                        />
                      )}
                    </CardContent>
                  </Card>
                )
              })}
            </div>
          )}
          <DataPagination
            meta={pagination}
            isFa={isFa}
            onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
          />
        </div>

        <aside className="min-w-0 xl:sticky xl:top-20 xl:self-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("rankTitle")}</CardTitle>
              <CardDescription>{tp("rankSubtitle")}</CardDescription>
            </CardHeader>
            <CardContent className="max-h-[70vh] space-y-2 overflow-y-auto pe-1">
              {ranked.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tp("rankEmpty")}</p>
              ) : (
                ranked.map((p, idx) => (
                  <div
                    key={String(p.id)}
                    className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <span className="shrink-0 tabular-nums text-muted-foreground">{idx + 1}.</span>
                      <span className="truncate font-medium">{String(p.name ?? "—")}</span>
                    </span>
                    <span className="shrink-0 tabular-nums font-semibold">
                      {formatNumber(num(p.userCount), isFa)}
                    </span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </aside>
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent
          side={isFa ? "left" : "right"}
          className="w-full overflow-y-auto sm:max-w-md"
          showCloseButton
        >
          <SheetHeader>
            <SheetTitle>{formMode === "add" ? tp("addPlan") : tp("editPlan")}</SheetTitle>
          </SheetHeader>
          <div className="flex flex-col gap-4 px-4 pb-4">
            <div className="space-y-2">
              <Label>{tp("planName")}</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              />
            </div>
            {hasWholesaleCatalog ? (
              <div className="space-y-2">
                <Label>{tp("wholesaleLine")}</Label>
                <select
                  className={selectClass}
                  value={String(form.wholesale_line_id || 0)}
                  onChange={(e) => {
                    const wlid = num(e.target.value)
                    const line = wholesaleLines.find((w) => num(w.id) === wlid)
                    const pid = num(line?.panel_id) || form.plan_panel_id
                    setForm((f) => ({
                      ...f,
                      wholesale_line_id: wlid,
                      plan_panel_id: pid,
                      category: firstCategoryForPanel(pid) || f.category,
                    }))
                  }}
                >
                  <option value="0">{isFa ? "—" : "—"}</option>
                  {wholesaleLines.map((w) => (
                    <option key={String(w.id)} value={String(num(w.id))}>
                      {String(w.label ?? w.id)}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-muted-foreground">{tp("wholesaleLineHint")}</p>
              </div>
            ) : (
              <div className="space-y-2">
                <Label>{tp("filterPanel")}</Label>
                <select
                  className={selectClass}
                  value={String(form.plan_panel_id)}
                  onChange={(e) => {
                    const pid = num(e.target.value)
                    setForm((f) => ({
                      ...f,
                      plan_panel_id: pid,
                      category: firstCategoryForPanel(pid) || f.category,
                    }))
                  }}
                >
                  {panels.map((p) => {
                    const canSell = panelCanSellPlan(p, resellerMode)
                    const base = String(p.label ?? p.name ?? p.id)
                    return (
                      <option
                        key={String(p.id)}
                        value={String(p.id)}
                        disabled={resellerMode && !canSell}
                      >
                        {base}
                        {resellerMode && !canSell ? tp("panelNoAccessSuffix") : ""}
                      </option>
                    )
                  })}
                </select>
              </div>
            )}
            <div className="space-y-2">
              <Label>{tp("category")}</Label>
              <select
                className={selectClass}
                value={form.category}
                onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
              >
                <option value="">{isFa ? "—" : "—"}</option>
                {categoriesForFormPanel.map((c) => (
                  <option key={String(c.id)} value={String(c.slug ?? "")}>
                    {String(c.label ?? c.slug)} ({String(c.slug)})
                  </option>
                ))}
              </select>
              {categoriesForFormPanel.length === 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">{tp("noCategories")}</p>
              ) : null}
            </div>
            {resellerMode && floorForPanel ? (
              <div className={cn("space-y-2 rounded-md border bg-muted/40 p-3 text-sm", isFa && "text-right")}>
                <p className="font-medium text-foreground">{tp("connectionPresetTitle")}</p>
                <p className="text-muted-foreground">{tp("connectionPresetHint")}</p>
                <p className="tabular-nums">
                  {tp("protocolXray")} · {tp("inbound")}: #{formatNumber(num(floorForPanel.default_inbound_id), isFa)}
                </p>
              </div>
            ) : null}
            {!resellerMode ? (
              <div className="space-y-2">
                <Label>{tp("inbound")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.inbound_id || ""}
                  onChange={(e) => setForm((f) => ({ ...f, inbound_id: num(e.target.value) }))}
                />
              </div>
            ) : null}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>{tp("duration")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.duration_days}
                  onChange={(e) => setForm((f) => ({ ...f, duration_days: num(e.target.value) }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("clients")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.clients_count}
                  onChange={(e) => setForm((f) => ({ ...f, clients_count: Math.max(0, num(e.target.value)) }))}
                />
                <p className="text-xs text-muted-foreground">{tp("clientsCountHint")}</p>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{tp("pricingType")}</Label>
              <select
                className={selectClass}
                value={form.plan_pricing_type}
                onChange={(e) =>
                  setForm((f) => ({
                    ...f,
                    plan_pricing_type: e.target.value === "per_gb" ? "per_gb" : "fixed",
                  }))
                }
              >
                <option value="fixed">{tp("pricingFixed")}</option>
                <option value="per_gb">{tp("pricingPerGb")}</option>
              </select>
            </div>
            {form.plan_pricing_type === "fixed" ? (
              <>
                <div className="space-y-2">
                  <Label>{tp("price")}</Label>
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.price}
                    onChange={(e) => setForm((f) => ({ ...f, price: num(e.target.value) }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("cardTraffic")} ({tp("gbSuffix")})</Label>
                  <Input
                    type="number"
                    min={0}
                    value={form.traffic_gb}
                    onChange={(e) => setForm((f) => ({ ...f, traffic_gb: num(e.target.value) }))}
                  />
                </div>
              </>
            ) : (
              <>
                <div className="space-y-2">
                  <Label>{tp("pricePerGb")}</Label>
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.price_per_gb}
                    onChange={(e) => setForm((f) => ({ ...f, price_per_gb: num(e.target.value) }))}
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label>{tp("trafficGbMin")}</Label>
                    <Input
                      type="number"
                      min={1}
                      value={form.traffic_gb_min}
                      onChange={(e) => setForm((f) => ({ ...f, traffic_gb_min: num(e.target.value) }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{tp("trafficGbMax")}</Label>
                    <Input
                      type="number"
                      min={1}
                      value={form.traffic_gb_max}
                      onChange={(e) => setForm((f) => ({ ...f, traffic_gb_max: num(e.target.value) }))}
                    />
                  </div>
                </div>
              </>
            )}
            {resellerMode && minPriceFloorPerGb > 0 ? (
              <div className="space-y-1 text-xs text-muted-foreground">
                {hasWholesaleCatalog && form.wholesale_line_id > 0 ? (
                  <>
                    <p>
                      {t("plansAdmin.minPriceTierRatePerGb", {
                        rate: formatNumber(minPriceFloorPerGb, isFa),
                      })}
                    </p>
                    {form.plan_pricing_type === "fixed" ? (
                      <p>
                        {t("plansAdmin.minPriceHintFixedTotal", {
                          min: formatNumber(
                            minPriceFloorPerGb * Math.max(1, form.traffic_gb || 1),
                            isFa
                          ),
                        })}
                      </p>
                    ) : (
                      <p>
                        {t("plansAdmin.minPriceHintPerGb", {
                          min: formatNumber(minPriceFloorPerGb, isFa),
                        })}
                      </p>
                    )}
                  </>
                ) : (
                  <p>
                    {form.plan_pricing_type === "per_gb"
                      ? t("plansAdmin.minPriceHintPerGb", {
                          min: formatNumber(minPriceFloorPerGb, isFa),
                        })
                      : t("plansAdmin.minPriceHintFixed", {
                          min: formatNumber(
                            minPriceFloorPerGb * Math.max(1, form.traffic_gb || 1),
                            isFa
                          ),
                        })}
                  </p>
                )}
              </div>
            ) : null}
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.plan_active}
                onChange={(e) => setForm((f) => ({ ...f, plan_active: e.target.checked }))}
              />
              {tp("active")}
            </label>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              disabled={saving || categoriesForFormPanel.length === 0}
              onClick={() => void onSaveSheet()}
            >
              {tp("save")}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      {showCatalogDefaultsSave ? (
        <Dialog open={catalogDialogOpen} onOpenChange={setCatalogDialogOpen}>
          <DialogContent className={cn("sm:max-w-md", isFa && "text-right [direction:rtl]")}>
            <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
              <DialogTitle>{tp("catalogDefaultsDialogTitle")}</DialogTitle>
              <DialogDescription>{tp("catalogCardDesc")}</DialogDescription>
            </DialogHeader>
            {catalogError ? (
              <p className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {catalogError}
              </p>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="catalog_concurrent_dialog">{tp("catalogConcurrent")}</Label>
                <Input
                  id="catalog_concurrent_dialog"
                  type="number"
                  min={0}
                  value={catalogForm.default_concurrent_users}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, default_concurrent_users: e.target.value }))
                  }
                />
                <p className="text-xs text-muted-foreground">{tp("clientsCountHint")}</p>
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="catalog_extra_price_dialog">{tp("catalogExtraPrice")}</Label>
                <Input
                  id="catalog_extra_price_dialog"
                  type="text"
                  inputMode="decimal"
                  value={catalogForm.price_per_extra_user}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, price_per_extra_user: e.target.value }))
                  }
                />
              </div>
            </div>
            <DialogFooter className={cn("gap-2", isFa && "sm:flex-row-reverse")}>
              <Button type="button" variant="outline" onClick={() => setCatalogDialogOpen(false)}>
                {tp("cancel")}
              </Button>
              <Button type="button" disabled={catalogSaving} onClick={() => void onSaveCatalogDefaults()}>
                {catalogSaving ? "…" : tp("catalogSave")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      ) : null}

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className={cn(isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDescription")}</DialogDescription>
          </DialogHeader>
          <DialogFooter className={cn("gap-2 sm:justify-between", isFa && "sm:flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void onConfirmDelete()}>
              {tp("deleteConfirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
        </>
      ) : null}
    </div>
  )
}

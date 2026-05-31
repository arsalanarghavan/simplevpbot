"use client"

import { useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { KeyRound, LayoutDashboard, LogIn, Search, Settings2, ShieldCheck } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { DataPagination } from "@/components/data-pagination"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function n(v: unknown): number {
  const x = Number(v)
  return Number.isFinite(x) ? x : 0
}

const PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
const ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩"

/** Normalize Persian/Arabic digits and separators for parseFloat. */
function normalizeNumericInput(raw: string): string {
  let s = String(raw)
    .trim()
    .replace(/[\u066C\u060C,]/g, "")
    .replace(/[\u066B\u06DF]/g, ".")
  for (let i = 0; i < 10; i++) {
    const d = String(i)
    s = s.split(PERSIAN_DIGITS[i]!).join(d)
    s = s.split(ARABIC_DIGITS[i]!).join(d)
  }
  return s.replace(/,/g, ".")
}

function parsePricePerGbToman(raw: string): number {
  const s = normalizeNumericInput(raw)
  if (!s) return 0
  const x = parseFloat(s)
  return Number.isFinite(x) ? x : 0
}

/** Show whole toman in inputs when the stored value is an integer (avoid 190000.0000). */
function formatTomanInputFromStored(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (!s) return ""
  const x = parsePricePerGbToman(s)
  if (!Number.isFinite(x) || x < 0) return s
  if (Math.abs(x - Math.round(x)) < 1e-6) return String(Math.round(x))
  const rounded = Math.round(x * 100) / 100
  return String(rounded)
}

function displayName(u: DashRecord): string {
  const name = `${String(u.first_name ?? "").trim()} ${String(u.last_name ?? "").trim()}`.trim()
  return name || String(u.username ?? "").trim() || "—"
}

const USER_STATUS_KEYS = new Set(["pending", "approved", "rejected", "blocked"])

function statusBadgeVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  const s = st.toLowerCase()
  if (s === "approved") return "default"
  if (s === "pending") return "secondary"
  if (s === "rejected") return "destructive"
  if (s === "blocked") return "outline"
  return "outline"
}

function resellerStatusLabel(t: (k: string, o?: { defaultValue?: string }) => string, raw: unknown): string {
  const st = String(raw ?? "").trim().toLowerCase()
  if (USER_STATUS_KEYS.has(st)) {
    return t(`usersAdmin.status_${st}`)
  }
  return String(raw ?? "").trim() || "—"
}

type PanelPriceRow = {
  panel_id: number
  price_per_gb: string
  panel_access: boolean
  default_service_type: "xray" | "l2tp"
  default_inbound_id: string
  default_l2tp_server_id: number
}

/** Matches backend: access if explicit allow or positive wholesale (legacy rows). */
function panelAllowedFromStoredRow(ex: Record<string, unknown> | undefined): boolean {
  if (!ex) return false
  const raw = ex.panel_access
  const acc = raw === true || raw === 1 || raw === "1"
  const price = parsePricePerGbToman(String(ex.price_per_gb ?? ""))
  return acc || price > 0
}

function isDigitOnlyQuery(raw: string): boolean {
  const t = raw.replace(/\s/g, "")
  if (!t) return false
  return /^[\d۰-۹٠-٩]+$/.test(t)
}

export function DashboardResellersAdmin({
  rows,
  panels,
  resellerPermissionsMap,
  resellerPanelPricesMap,
  resellersSearchQuery = "",
  resellersStatusFilter = "all",
  onResellersFiltersChange,
  canManageResellerControls = true,
  canManagePanelPrices = canManageResellerControls,
  isFa,
  pagination,
  onPageChange,
  onPerPageChange,
  onOpenUserDetail,
  onOpenWorkspace,
  onMutateSuccess,
  onImpersonateReseller,
}: {
  rows: DashRecord[]
  panels: DashRecord[]
  resellersSearchQuery?: string
  resellersStatusFilter?: string
  onResellersFiltersChange?: (patch: { q?: string; status?: string }) => void
  resellerPermissionsMap?: Record<string, Record<string, boolean> | undefined>
  resellerPanelPricesMap?: Record<string, Array<Record<string, unknown>> | undefined>
  resellerBotMap?: Record<string, { enabled?: boolean; brand?: string } | undefined>
  canManageResellerControls?: boolean
  canManagePanelPrices?: boolean
  isFa: boolean
  pagination: PaginationMeta | null
  onPageChange: (p: number) => void
  onPerPageChange: (n: number) => void
  onOpenUserDetail: (id: number) => void
  onOpenWorkspace?: (id: number) => void
  onMutateSuccess?: () => void
  onImpersonateReseller?: (id: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`resellersAdmin.${k}`, opts)
  const isResellerActor = Boolean(window.__SIMPLEVPBOT_DASH__?.isReseller)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [panelPriceErr, setPanelPriceErr] = useState("")
  const [panelPriceNotice, setPanelPriceNotice] = useState("")
  const [createOpen, setCreateOpen] = useState(false)
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    username: "",
    dashboard_password: "",
    phone: "",
    tg_user_id: "",
    bale_user_id: "",
  })
  const [priceResellerId, setPriceResellerId] = useState<number | null>(null)
  const [priceRows, setPriceRows] = useState<PanelPriceRow[]>([])
  const [permResellerId, setPermResellerId] = useState<number | null>(null)
  const [permissions, setPermissions] = useState<Record<string, boolean>>({})
  const [searchDraft, setSearchDraft] = useState(resellersSearchQuery)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    setSearchDraft(resellersSearchQuery)
  }, [resellersSearchQuery])

  useEffect(() => {
    if (!onResellersFiltersChange) return
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      const effective =
        next !== "" && !isDigitOnlyQuery(next) && next.length < 2 ? "" : next
      if (effective !== resellersSearchQuery.trim()) {
        onResellersFiltersChange({ q: effective })
      }
    }, 300)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [searchDraft, resellersSearchQuery, onResellersFiltersChange])

  const directUsersCount = useMemo(() => {
    const m = new Map<number, number>()
    for (const r of rows) {
      m.set(n(r.id), n(r.direct_users_count))
    }
    return m
  }, [rows])

  const canSubmitCreate = useMemo(() => {
    const u = form.username.trim()
    const pw = form.dashboard_password
    const hasDash = u.length > 0 && pw.length >= 6
    const hasBot = n(form.tg_user_id) > 0 || n(form.bale_user_id) > 0
    return hasDash || hasBot
  }, [form.username, form.dashboard_password, form.tg_user_id, form.bale_user_id])

  function openPriceDialog(rid: number) {
    setPanelPriceErr("")
    setPanelPriceNotice("")
    setPriceResellerId(rid)
    const existingRows = resellerPanelPricesMap?.[String(rid)] ?? []
    const existingByPanel = new Map<number, string>()
    for (const row of existingRows) {
      const pid = n(row?.panel_id)
      if (pid > 0) existingByPanel.set(pid, String(row?.price_per_gb ?? ""))
    }
    setPriceRows(
      panels.map((p) => {
        const pid = n(p.id)
        const ex = existingRows.find((x) => n(x.panel_id) === pid) as Record<string, unknown> | undefined
        const stRaw = String(ex?.default_service_type ?? "xray").toLowerCase()
        return {
          panel_id: pid,
          price_per_gb: formatTomanInputFromStored(existingByPanel.get(pid) ?? ""),
          panel_access: panelAllowedFromStoredRow(ex),
          default_service_type: stRaw === "l2tp" ? "l2tp" : "xray",
          default_inbound_id: String(ex?.default_inbound_id ?? ""),
          default_l2tp_server_id: n(ex?.default_l2tp_server_id),
        }
      })
    )
  }

  async function savePrices() {
    if (priceResellerId == null) return
    setBusy(true)
    setPanelPriceErr("")
    try {
      const rows = isResellerActor
        ? priceRows
            .filter((r) => r.panel_access)
            .map((r) => ({
              panel_id: r.panel_id,
              price_per_gb: parsePricePerGbToman(String(r.price_per_gb)),
            }))
        : priceRows
            .filter((r) => r.panel_access)
            .map((r) => {
              const priceNum = parsePricePerGbToman(String(r.price_per_gb))
              return {
                panel_id: r.panel_id,
                price_per_gb: priceNum,
                panel_access: true,
                default_service_type: r.default_service_type,
                default_inbound_id: Math.max(0, parseInt(String(r.default_inbound_id).trim(), 10) || 0),
                default_l2tp_server_id:
                  r.default_service_type === "l2tp" ? Math.max(0, r.default_l2tp_server_id) : 0,
              }
            })
      const res = await postAdminMutate("reseller_panel_prices_save", {
        reseller_svp_user_id: priceResellerId,
        rows,
      })
      if (!res.ok) {
        setPanelPriceErr(
          res.message === "no_valid_panels" ? tp("panelPricesSaveNoValidPanels") : res.message || tp("createError")
        )
        return
      }
      const noticeParts: string[] = []
      if (res.skipped_panel_ids?.length) {
        noticeParts.push(tp("panelPricesSkippedUnknownPanels", { ids: res.skipped_panel_ids.join(", ") }))
      }
      if (isResellerActor) {
        noticeParts.push(tp("panelPricesParentFloorSavedHint"))
      }
      setPriceResellerId(null)
      if (noticeParts.length) setPanelPriceNotice(noticeParts.join(" "))
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function createReseller() {
    setBusy(true)
    setErr("")
    try {
      const payload: Record<string, unknown> = {
        role: "reseller",
        status: "approved",
        first_name: form.first_name,
        last_name: form.last_name,
        username: form.username,
        phone: form.phone,
        tg_user_id: form.tg_user_id,
        bale_user_id: form.bale_user_id,
      }
      if (form.dashboard_password.length >= 6) {
        payload.dashboard_password = form.dashboard_password
      }
      const res = await postAdminMutate("user_manual_create", payload)
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setForm({
        first_name: "",
        last_name: "",
        username: "",
        dashboard_password: "",
        phone: "",
        tg_user_id: "",
        bale_user_id: "",
      })
      setCreateOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const permDefs = useMemo(
    () => [
      { key: "users.manage", label: tp("perm_users_manage") },
      { key: "users.bulk", label: tp("perm_users_bulk") },
      { key: "broadcast.send", label: tp("perm_broadcast_send") },
      { key: "receipts.review", label: tp("perm_receipts_review") },
      { key: "plans.manage", label: tp("perm_plans_manage") },
      { key: "services.manage", label: tp("perm_services_manage") },
    ],
    [tp])

  async function savePermissions() {
    if (permResellerId == null) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("reseller_permissions_save", {
        reseller_svp_user_id: permResellerId,
        permissions,
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setPermResellerId(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const inputAlignClass = "text-start"
  const statusFilter = resellersStatusFilter || "all"

  return (
    <div className={dashPageRootClass(isFa, "space-y-4")} dir={dashDir(isFa)}>
      <DashboardPageHeader
        title={tp("title")}
        description={tp("subtitle")}
        actions={
          <Button
            type="button"
            disabled={!canManageResellerControls}
            onClick={() => {
              setErr("")
              setCreateOpen(true)
            }}
          >
            {tp("createTitle")}
          </Button>
        }
      />

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className={cn("sm:max-w-2xl", isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("createTitle")}</DialogTitle>
            <DialogDescription>{tp("createHint")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-2 md:grid-cols-2">
            <Input
              placeholder={tp("firstName")}
              className={inputAlignClass}
              value={form.first_name}
              onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))}
            />
            <Input
              placeholder={tp("lastName")}
              className={inputAlignClass}
              value={form.last_name}
              onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))}
            />
            <Input
              placeholder={tp("dashboardUsername")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.username}
              onChange={(e) => setForm((p) => ({ ...p, username: e.target.value }))}
            />
            <Input
              placeholder={tp("dashboardPassword")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              type="password"
              autoComplete="new-password"
              value={form.dashboard_password}
              onChange={(e) => setForm((p) => ({ ...p, dashboard_password: e.target.value }))}
            />
            <Input
              placeholder={tp("phone")}
              dir="ltr"
              className={inputAlignClass}
              value={form.phone}
              onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
            />
            <Input
              placeholder={tp("tgUserId")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.tg_user_id}
              onChange={(e) => setForm((p) => ({ ...p, tg_user_id: e.target.value }))}
            />
            <Input
              placeholder={tp("baleUserId")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.bale_user_id}
              onChange={(e) => setForm((p) => ({ ...p, bale_user_id: e.target.value }))}
            />
          </div>
          {err ? <p className="text-sm text-destructive">{err}</p> : null}
          <DialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
              {t("a11y.close")}
            </Button>
            <Button
              type="button"
              disabled={busy || !canManageResellerControls || !canSubmitCreate}
              onClick={() => void createReseller()}
            >
              {tp("create")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <div className={cn("flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end")}>
        <div className="relative min-w-0 flex-1 sm:max-w-md">
          <Search className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground ltr:left-3 rtl:right-3" />
          <Input
            className={cn("h-9", isFa ? "pr-9 pl-3 text-right" : "pl-9 pr-3")}
            placeholder={tp("searchPlaceholder")}
            value={searchDraft}
            onChange={(e) => setSearchDraft(e.target.value)}
          />
        </div>
        <div className="flex min-w-[10rem] flex-col gap-1">
          <Label className="text-xs text-muted-foreground">{tp("filterStatus")}</Label>
          <select
            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs"
            value={statusFilter}
            onChange={(e) => onResellersFiltersChange?.({ status: e.target.value })}
          >
            <option value="all">{tp("filterStatusAll")}</option>
            <option value="pending">{t("usersAdmin.status_pending")}</option>
            <option value="approved">{t("usersAdmin.status_approved")}</option>
            <option value="rejected">{t("usersAdmin.status_rejected")}</option>
            <option value="blocked">{t("usersAdmin.status_blocked")}</option>
          </select>
        </div>
      </div>

      <Card>
        <CardHeader className={cn("flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between")}>
          <div className={cn("space-y-1", isFa && "text-right")}>
            <CardTitle className="text-base">{tp("listTitle")}</CardTitle>
            {pagination && pagination.total > 0 ? (
              <p className="text-xs text-muted-foreground">{tp("listCount", { n: pagination.total })}</p>
            ) : null}
          </div>
          {panelPriceNotice ? (
            <p className="text-sm text-amber-900 dark:text-amber-100">{panelPriceNotice}</p>
          ) : null}
        </CardHeader>
        <CardContent>
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("empty")}</p>
          ) : (
            <>
              <div className="space-y-3 md:hidden">
                {rows.map((r) => {
                  const id = n(r.id)
                  return (
                    <Card key={id}>
                      <CardContent className="space-y-3 p-4 text-sm">
                        <div className={cn("flex flex-wrap items-start justify-between gap-2")}>
                          <div className={cn("min-w-0 space-y-1", isFa && "text-right")}>
                            <p className="font-medium">{displayName(r)}</p>
                            <p className="font-mono text-xs text-muted-foreground" dir="ltr">
                              #{id}
                            </p>
                          </div>
                          <Badge variant={statusBadgeVariant(String(r.status ?? ""))} className="shrink-0">
                            {resellerStatusLabel(t, r.status)}
                          </Badge>
                        </div>
                        <div className={cn("grid grid-cols-2 gap-2 text-xs text-muted-foreground", isFa && "text-right")}>
                          <span>{tp("colUsers")}: {directUsersCount.get(id) ?? 0}</span>
                          <span>{t("usersAdmin.colPhone")}: {String(r.phone ?? "—")}</span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <Button type="button" variant="outline" size="icon" onClick={() => onOpenUserDetail(id)} aria-label={tp("manage")}>
                            <KeyRound className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="outline" size="icon" onClick={() => onOpenWorkspace?.(id)} aria-label={t("sidebar.groups.resellerWorkspace")}>
                            <LayoutDashboard className="h-4 w-4" />
                          </Button>
                          {onImpersonateReseller && canManageResellerControls ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              onClick={() => onImpersonateReseller(id)}
                              aria-label={tp("impersonateReseller")}
                            >
                              <LogIn className="h-4 w-4" />
                            </Button>
                          ) : null}
                          <Button type="button" variant="outline" size="icon" onClick={() => openPriceDialog(id)} disabled={!canManagePanelPrices || panels.length < 1} aria-label={tp("panelPrices")}>
                            <Settings2 className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="outline" size="icon" onClick={() => { setPermResellerId(id); setPermissions({ ...(resellerPermissionsMap?.[String(id)] ?? {}) }) }} disabled={!canManageResellerControls} aria-label={tp("permissionsColumn")}>
                            <ShieldCheck className="h-4 w-4" />
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  )
                })}
              </div>
              <div className="hidden overflow-x-auto rounded-md border md:block">
                <table
                  className="w-full min-w-[44rem] table-fixed text-sm"
                  dir={dashDir(isFa)}
                >
                  <thead>
                    <tr className="bg-muted/40">
                      <th className="p-2 text-start">{tp("colId")}</th>
                      <th className="p-2 text-start">{tp("colName")}</th>
                      <th className="p-2 text-start">{tp("colStatus")}</th>
                      <th className="p-2 text-start">{tp("colUsers")}</th>
                      <th className="p-2 text-start w-[11rem]">{tp("colActions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => {
                      const id = n(r.id)
                      return (
                        <tr key={id} className="border-t">
                          <td className="p-2 font-mono text-start" dir="ltr">
                            {id}
                          </td>
                          <td className="p-2 text-start">
                            <div className="space-y-0.5">
                              <div>{displayName(r)}</div>
                              <div className="text-xs text-muted-foreground">{String(r.phone ?? "—")}</div>
                            </div>
                          </td>
                          <td className="p-2 text-start">
                            <Badge variant={statusBadgeVariant(String(r.status ?? ""))} className="font-normal">
                              {resellerStatusLabel(t, r.status)}
                            </Badge>
                          </td>
                          <td className="p-2 tabular-nums text-start">{directUsersCount.get(id) ?? 0}</td>
                          <td className="p-2 text-start">
                            <TooltipProvider>
                              <div className={cn("flex flex-wrap gap-1", isFa && "text-right")}>
                                <Tooltip><TooltipTrigger asChild><Button type="button" variant="ghost" size="icon" onClick={() => onOpenUserDetail(id)}><KeyRound className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{tp("manage")}</TooltipContent></Tooltip>
                                <Tooltip><TooltipTrigger asChild><Button type="button" variant="ghost" size="icon" onClick={() => onOpenWorkspace?.(id)}><LayoutDashboard className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t("sidebar.groups.resellerWorkspace")}</TooltipContent></Tooltip>
                                {onImpersonateReseller && canManageResellerControls ? (
                                  <Tooltip>
                                    <TooltipTrigger asChild>
                                      <Button type="button" variant="ghost" size="icon" onClick={() => onImpersonateReseller(id)}>
                                        <LogIn className="h-4 w-4" />
                                      </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{tp("impersonateReseller")}</TooltipContent>
                                  </Tooltip>
                                ) : null}
                                <Tooltip><TooltipTrigger asChild><Button type="button" variant="ghost" size="icon" onClick={() => openPriceDialog(id)} disabled={!canManagePanelPrices || panels.length < 1}><Settings2 className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{tp("panelPrices")}</TooltipContent></Tooltip>
                                <Tooltip><TooltipTrigger asChild><Button type="button" variant="ghost" size="icon" onClick={() => { setPermResellerId(id); setPermissions({ ...(resellerPermissionsMap?.[String(id)] ?? {}) }) }} disabled={!canManageResellerControls}><ShieldCheck className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{tp("permissionsColumn")}</TooltipContent></Tooltip>
                              </div>
                            </TooltipProvider>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <DataPagination
            meta={pagination}
            isFa={isFa}
            onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>

      <Dialog
        open={priceResellerId != null}
        onOpenChange={(o) => {
          if (!o) {
            setPriceResellerId(null)
            setPanelPriceErr("")
          }
        }}
      >
        <DialogContent className={cn("max-h-[85vh] overflow-y-auto sm:max-w-2xl", isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle className={cn("flex items-center gap-2")}>
              <span>{tp("panelPricesTitle")}</span>
              {isResellerActor ? (
                <Badge variant="secondary" className="font-normal">
                  {tp("panelPricesParentFloorBadge")}
                </Badge>
              ) : null}
            </DialogTitle>
            <DialogDescription className="space-y-2">
              <span>
                {t(
                  isResellerActor
                    ? "resellersAdmin.panelPricesDialogDescriptionParentFloor"
                    : "resellersAdmin.panelPricesDialogDescription",
                  { id: priceResellerId ?? 0 }
                )}
              </span>
              <span className="block text-muted-foreground">
                {t(
                  isResellerActor
                    ? "resellersAdmin.panelPricesDialogHintParentFloor"
                    : "resellersAdmin.panelPricesDialogHintAdmin"
                )}
              </span>
              {isResellerActor ? (
                <span className="block rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-muted-foreground">
                  {tp("panelPricesParentCatalogNote")}
                </span>
              ) : null}
            </DialogDescription>
          </DialogHeader>
          {panelPriceErr ? (
            <p className="text-sm text-destructive">{panelPriceErr}</p>
          ) : null}
          <div className="grid gap-2 py-2">
            {priceRows.map((row, idx) => {
              const pl = panels.find((p) => n(p.id) === row.panel_id)
              const label = String(pl?.label ?? pl?.name ?? `Panel ${row.panel_id}`)
              return (
                <div
                  key={row.panel_id}
                  className={cn(
                    "flex flex-col gap-2 rounded-md border border-border/60 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                  )}
                >
                  <span className="min-w-0 flex-1 text-sm font-medium leading-snug">{label}</span>
                  <div className={cn("flex flex-wrap items-center gap-3", isFa && "text-right")}>
                    <Switch
                      id={`panel-access-${row.panel_id}`}
                      checked={priceRows[idx]?.panel_access ?? false}
                      onCheckedChange={(checked) => {
                        setPriceRows((prev) => prev.map((r, i) => (i === idx ? { ...r, panel_access: checked } : r)))
                      }}
                      aria-label={tp("panelAccessToggleAria", { label })}
                    />
                    {(priceRows[idx]?.panel_access ?? false) ? (
                      <div className={cn("flex min-w-[9rem] flex-col gap-1", isFa && "items-end")}>
                        <Label htmlFor={`panel-price-${row.panel_id}`} className="text-xs text-muted-foreground">
                          {t("pricePerGb")}
                        </Label>
                        <Input
                          id={`panel-price-${row.panel_id}`}
                          type="text"
                          inputMode="decimal"
                          className={cn("h-8 w-full max-w-[10rem]", isFa && "text-right")}
                          value={priceRows[idx]?.price_per_gb ?? ""}
                          onChange={(e) => {
                            const v = e.target.value
                            setPriceRows((prev) =>
                              prev.map((r, i) => (i === idx ? { ...r, price_per_gb: v } : r))
                            )
                          }}
                          placeholder={tp("panelPricesIncludePanelFloor")}
                          disabled={busy}
                        />
                      </div>
                    ) : null}
                  </div>
                </div>
              )
            })}
          </div>
          <DialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setPriceResellerId(null)}>
              {t("a11y.close")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void savePrices()}>
              {tp("panelPricesSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={permResellerId != null} onOpenChange={(o) => !o && setPermResellerId(null)}>
        <DialogContent className={cn("max-h-[85vh] overflow-y-auto sm:max-w-lg", isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("permissionsDialogTitle")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {permDefs.map((p) => (
              <label key={p.key} className={cn("flex items-center gap-2 text-sm", isFa && "text-right")}>
                <input
                  type="checkbox"
                  checked={permissions[p.key] !== false}
                  onChange={(e) => setPermissions((prev) => ({ ...prev, [p.key]: e.target.checked }))}
                />
                {p.label}
              </label>
            ))}
          </div>
          <DialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setPermResellerId(null)}>
              {t("a11y.close")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void savePermissions()}>
              {tp("permissionsSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

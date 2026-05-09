"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { KeyRound, LayoutDashboard, LogIn, Settings2, ShieldCheck } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { dashContentClass, dashFlexRowClass } from "@/lib/dash-locale"
import { DataPagination } from "@/components/data-pagination"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

const selectClass =
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

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

export function DashboardResellersAdmin({
  rows,
  panels,
  l2tpServers = [],
  resellerPermissionsMap,
  resellerPanelPricesMap,
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
  l2tpServers?: DashRecord[]
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
  const tp = (k: string) => t(`resellersAdmin.${k}`)
  const isResellerActor = Boolean(window.__SIMPLEVPBOT_DASH__?.isReseller)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
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
          panel_access: ((ex?.panel_access ?? 1) as number | boolean) !== 0,
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
    setErr("")
    try {
      const rows = priceRows
        .map((r) => {
          const priceNum = parsePricePerGbToman(String(r.price_per_gb))
          const panel_access = priceNum > 0 ? true : r.panel_access
          const base: Record<string, unknown> = {
            panel_id: r.panel_id,
            price_per_gb: priceNum,
            panel_access,
          }
          if (!isResellerActor) {
            base.default_service_type = r.default_service_type
            base.default_inbound_id = Math.max(0, parseInt(String(r.default_inbound_id).trim(), 10) || 0)
            base.default_l2tp_server_id =
              r.default_service_type === "l2tp" ? Math.max(0, r.default_l2tp_server_id) : 0
          }
          return base
        })
        .filter((r) => (r.panel_access as boolean) || (r.price_per_gb as number) > 0)
      if (rows.length === 0) {
        setErr(tp("panelPricesNoRowsError"))
        return
      }
      const res = await postAdminMutate("reseller_panel_prices_save", {
        reseller_svp_user_id: priceResellerId,
        rows,
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setPriceResellerId(null)
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
    [tp],
  )

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

  const flexRow = dashFlexRowClass(isFa)

  return (
    <div className={cn("space-y-4", dashContentClass(isFa))}>
      <h2 className="text-lg font-medium">{tp("title")}</h2>
      <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      <Card>
        <CardHeader className={cn(isFa && "text-right sm:text-right")}>
          <CardTitle className="text-base">{tp("createTitle")}</CardTitle>
          <CardDescription>{tp("createHint")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-2">
          <Input
            placeholder={tp("firstName")}
            className={isFa ? "text-right" : "text-left"}
            value={form.first_name}
            onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))}
          />
          <Input
            placeholder={tp("lastName")}
            className={isFa ? "text-right" : "text-left"}
            value={form.last_name}
            onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))}
          />
          <Input
            placeholder={tp("dashboardUsername")}
            dir="ltr"
            className="text-left font-mono"
            value={form.username}
            onChange={(e) => setForm((p) => ({ ...p, username: e.target.value }))}
          />
          <Input
            placeholder={tp("dashboardPassword")}
            dir="ltr"
            className="text-left font-mono"
            type="password"
            autoComplete="new-password"
            value={form.dashboard_password}
            onChange={(e) => setForm((p) => ({ ...p, dashboard_password: e.target.value }))}
          />
          <Input
            placeholder={tp("phone")}
            className={isFa ? "text-right" : "text-left"}
            dir="ltr"
            value={form.phone}
            onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
          />
          <Input
            placeholder={tp("tgUserId")}
            dir="ltr"
            className="text-left font-mono"
            value={form.tg_user_id}
            onChange={(e) => setForm((p) => ({ ...p, tg_user_id: e.target.value }))}
          />
          <Input
            placeholder={tp("baleUserId")}
            dir="ltr"
            className="text-left font-mono"
            value={form.bale_user_id}
            onChange={(e) => setForm((p) => ({ ...p, bale_user_id: e.target.value }))}
          />
          <div className={cn("md:col-span-2", flexRow, isFa ? "justify-end" : "justify-start")}>
            <Button
              type="button"
              disabled={busy || !canManageResellerControls || !canSubmitCreate}
              onClick={() => void createReseller()}
            >
              {tp("create")}
            </Button>
            {err ? <span className="text-sm text-destructive">{err}</span> : null}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className={cn("flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between", flexRow)}>
          <CardTitle className="text-base">{tp("listTitle")}</CardTitle>
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
                        <div className={cn("flex flex-wrap items-start justify-between gap-2", flexRow)}>
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
                <table className="w-full min-w-[44rem] table-fixed text-sm">
                  <thead>
                    <tr className="bg-muted/40">
                      <th className={cn("p-2", isFa ? "text-end" : "text-start")}>{tp("colId")}</th>
                      <th className={cn("p-2", isFa ? "text-end" : "text-start")}>{tp("colName")}</th>
                      <th className={cn("p-2", isFa ? "text-end" : "text-start")}>{tp("colStatus")}</th>
                      <th className={cn("p-2", isFa ? "text-end" : "text-start")}>{tp("colUsers")}</th>
                      <th className={cn("p-2", isFa ? "text-end" : "text-start")}>{tp("colActions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => {
                      const id = n(r.id)
                      return (
                        <tr key={id} className="border-t">
                          <td className={cn("p-2 font-mono", isFa ? "text-end" : "text-start")} dir="ltr">
                            {id}
                          </td>
                          <td className={cn("p-2", isFa ? "text-end" : "text-start")}>
                            <div className="space-y-0.5">
                              <div>{displayName(r)}</div>
                              <div className="text-xs text-muted-foreground">{String(r.phone ?? "—")}</div>
                            </div>
                          </td>
                          <td className={cn("p-2", isFa ? "text-end" : "text-start")}>
                            <Badge variant={statusBadgeVariant(String(r.status ?? ""))} className="font-normal">
                              {resellerStatusLabel(t, r.status)}
                            </Badge>
                          </td>
                          <td className={cn("p-2 tabular-nums", isFa ? "text-end" : "text-start")}>{directUsersCount.get(id) ?? 0}</td>
                          <td className={cn("p-2", isFa ? "text-end" : "text-start")}>
                            <TooltipProvider>
                              <div className={cn("flex flex-wrap gap-1", isFa && "flex-row-reverse justify-end")}>
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

      <Dialog open={priceResellerId != null} onOpenChange={(o) => !o && setPriceResellerId(null)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle className={cn("flex items-center gap-2", isFa && "flex-row-reverse")}>
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
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            {priceRows.map((row, idx) => {
              const pl = panels.find((p) => n(p.id) === row.panel_id)
              const label = String(pl?.label ?? pl?.name ?? `Panel ${row.panel_id}`)
              return (
                <div key={row.panel_id} className="grid gap-2 rounded-md border border-border/60 p-3">
                  <Label htmlFor={`ppb-${row.panel_id}`}>{label}</Label>
                  <label className={cn("flex items-center gap-2 text-xs", isFa && "flex-row-reverse justify-end")}>
                    <input
                      type="checkbox"
                      checked={priceRows[idx]?.panel_access ?? true}
                      onChange={(e) => {
                        const checked = e.target.checked
                        setPriceRows((prev) => prev.map((r, i) => (i === idx ? { ...r, panel_access: checked } : r)))
                      }}
                    />
                    {isResellerActor ? tp("panelPricesIncludePanelFloor") : tp("panelAccessLabel")}
                  </label>
                  <Input
                    id={`ppb-${row.panel_id}`}
                    dir="ltr"
                    value={priceRows[idx]?.price_per_gb ?? ""}
                    onChange={(e) => {
                      const v = e.target.value
                      setPriceRows((prev) => prev.map((r, i) => (i === idx ? { ...r, price_per_gb: v } : r)))
                    }}
                    placeholder={tp("pricePlaceholder")}
                  />
                  {!isResellerActor ? (
                    <div className={cn("grid gap-2 sm:grid-cols-2", isFa && "text-right")}>
                      <div className="space-y-1 sm:col-span-2">
                        <Label className="text-xs">{tp("defaultServiceType")}</Label>
                        <select
                          className={selectClass}
                          dir="ltr"
                          value={priceRows[idx]?.default_service_type ?? "xray"}
                          onChange={(e) => {
                            const v = e.target.value === "l2tp" ? "l2tp" : "xray"
                            setPriceRows((prev) =>
                              prev.map((r, i) => (i === idx ? { ...r, default_service_type: v } : r))
                            )
                          }}
                        >
                          <option value="xray">{t("plansAdmin.protocolXray")}</option>
                          <option value="l2tp">{t("plansAdmin.protocolL2tp")}</option>
                        </select>
                      </div>
                      {priceRows[idx]?.default_service_type === "l2tp" ? (
                        <div className="space-y-1 sm:col-span-2">
                          <Label className="text-xs">{tp("defaultL2tpServer")}</Label>
                          <select
                            className={selectClass}
                            dir="ltr"
                            value={String(priceRows[idx]?.default_l2tp_server_id ?? 0)}
                            onChange={(e) => {
                              const v = n(e.target.value)
                              setPriceRows((prev) =>
                                prev.map((r, i) => (i === idx ? { ...r, default_l2tp_server_id: v } : r))
                              )
                            }}
                          >
                            <option value="0">—</option>
                            {l2tpServers.map((s) => (
                              <option key={String(s.id)} value={String(n(s.id))}>
                                #{formatNumber(n(s.id), isFa)} {String(s.name ?? s.host ?? "")}
                              </option>
                            ))}
                          </select>
                        </div>
                      ) : (
                        <div className="space-y-1 sm:col-span-2">
                          <Label className="text-xs">{tp("defaultInbound")}</Label>
                          <Input
                            dir="ltr"
                            className="font-mono text-sm"
                            inputMode="numeric"
                            value={priceRows[idx]?.default_inbound_id ?? ""}
                            onChange={(e) => {
                              const v = e.target.value
                              setPriceRows((prev) =>
                                prev.map((r, i) => (i === idx ? { ...r, default_inbound_id: v } : r))
                              )
                            }}
                          />
                        </div>
                      )}
                    </div>
                  ) : null}
                </div>
              )
            })}
          </div>
          <DialogFooter>
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
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{tp("permissionsDialogTitle")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {permDefs.map((p) => (
              <label key={p.key} className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse justify-end")}>
                <input
                  type="checkbox"
                  checked={permissions[p.key] !== false}
                  onChange={(e) => setPermissions((prev) => ({ ...prev, [p.key]: e.target.checked }))}
                />
                {p.label}
              </label>
            ))}
          </div>
          <DialogFooter>
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

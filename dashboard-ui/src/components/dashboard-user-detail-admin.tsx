"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  Ban,
  CheckCircle2,
  ExternalLink,
  Hash,
  MessageSquare,
  Minus,
  PackagePlus,
  Plus,
  Radio,
  RotateCcw,
  Send,
  ShieldOff,
  Store,
  Wallet,
  XCircle,
} from "lucide-react"

import {
  DashboardUserServiceCard,
  ServiceActionDialog,
  type ServiceActionDlg,
} from "@/components/dashboard-user-service-card"

import { DataPagination } from "@/components/data-pagination"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
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
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"
import {
  formatDateTime,
  formatDigits,
  formatNumber,
  formatPlainLatinInt,
} from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { parsePaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

const actionBar =
  "flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/20 p-2"

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function displayName(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  const un = String(u.username ?? "").trim()
  return un || "—"
}

function statusVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "pending") return "secondary"
  if (st === "rejected") return "destructive"
  if (st === "blocked") return "outline"
  return "outline"
}

function isPerGbPlan(p: DashRecord | undefined): boolean {
  return String(p?.pricing_type ?? "") === "per_gb"
}

function previewCreatePriceToman(plan: DashRecord | undefined, volGbStr: string): number | null {
  if (!plan) return null
  const v = parseInt(volGbStr.trim(), 10)
  if (isPerGbPlan(plan)) {
    if (!Number.isFinite(v) || v < 1) return null
    const min = num(plan.traffic_gb_min)
    const max = num(plan.traffic_gb_max)
    if (min > 0 && max > 0 && (v < min || v > max)) return null
    return Math.round(num(plan.price_per_gb) * v * 100) / 100
  }
  return Math.round(num(plan.price) * 100) / 100
}

function formatUserActivityLine(
  row: DashRecord,
  t: (k: string, opts?: Record<string, string | number>) => string
): string {
  const ev = String(row.event_type ?? "")
  const pl =
    row.payload && typeof row.payload === "object"
      ? (row.payload as Record<string, unknown>)
      : {}
  const g = (k: string) => String(pl[k] ?? "")
  const gn = (k: string) => num(pl[k])

  switch (ev) {
    case "balance_delta":
      return t("userDetailAdmin.activity_balance_delta", {
        delta: formatPlainLatinInt(Math.round(gn("delta") * 100) / 100),
        after: formatPlainLatinInt(Math.round(gn("balance_after") * 100) / 100),
      })
    case "service_create":
      return t("userDetailAdmin.activity_service_create", {
        plan: g("plan_id"),
        mode: g("mode"),
        service: g("service_id"),
      })
    case "service_renew":
      return t("userDetailAdmin.activity_service_renew", { service: g("service_id"), mode: g("mode") })
    case "service_add_volume":
      return t("userDetailAdmin.activity_service_add_volume", {
        service: g("service_id"),
        gb: g("extra_gb"),
        mode: g("mode"),
      })
    case "service_reduce_volume":
      return t("userDetailAdmin.activity_service_reduce_volume", {
        service: g("service_id"),
        gb: g("reduce_gb"),
      })
    case "service_reduce_days":
      return t("userDetailAdmin.activity_service_reduce_days", {
        service: g("service_id"),
        days: g("days"),
      })
    case "service_add_days":
      return t("userDetailAdmin.activity_service_add_days", {
        service: g("service_id"),
        days: g("days"),
      })
    case "service_transfer_out":
      return t("userDetailAdmin.activity_service_transfer_out", {
        service: g("service_id"),
        target: g("target_id") || g("target_raw"),
      })
    case "service_transfer_in":
      return t("userDetailAdmin.activity_service_transfer_in", {
        service: g("service_id"),
        from: g("previous_user"),
      })
    case "service_soft_delete":
      return t("userDetailAdmin.activity_service_soft_delete", { service: g("service_id") })
    case "link_wp_user":
      return t("userDetailAdmin.activity_link_wp_user", { wp: g("wp_user_id") })
    case "user_ban":
      return t("userDetailAdmin.activity_user_ban")
    case "user_unban":
      return t("userDetailAdmin.activity_user_unban")
    case "admin_message":
      return t("userDetailAdmin.activity_admin_message", { channel: g("channel"), len: gn("length") })
    case "service_panel_sync":
      return t("userDetailAdmin.activity_service_panel_sync", { service: g("service_id") })
    case "service_regen_key":
      return t("userDetailAdmin.activity_service_regen_key", { service: g("service_id") })
    case "service_panel_refresh":
      return t("userDetailAdmin.activity_service_panel_refresh", { service: g("service_id") })
    case "service_panel_delete_client":
      return t("userDetailAdmin.activity_service_panel_delete_client", { service: g("service_id") })
    case "service_add_user_slots":
      return t("userDetailAdmin.activity_service_add_user_slots", {
        service: g("service_id"),
        n: g("extra_users"),
      })
    case "service_reduce_user_slots":
      return t("userDetailAdmin.activity_service_reduce_user_slots", {
        service: g("service_id"),
        n: g("reduce_users"),
      })
    case "service_set_limit_ip":
      return t("userDetailAdmin.activity_service_set_limit_ip", {
        service: g("service_id"),
        n: g("limit_ip"),
      })
    case "service_alerts_patch":
      return t("userDetailAdmin.activity_service_alerts_patch", { service: g("service_id") })
    case "callback_query":
      return t("userDetailAdmin.activity_callback_query", { data: g("callback_data").slice(0, 80) })
    case "command":
      return t("userDetailAdmin.activity_command", { cmd: g("command") })
    case "message":
      return t("userDetailAdmin.activity_message", { preview: g("text_preview").slice(0, 120) })
    default:
      return t("userDetailAdmin.activity_generic", { event: ev || "—" })
  }
}

export function DashboardUserDetailAdmin({
  userId,
  plans,
  settings,
  isFa,
  isReseller = false,
  onBack,
  onMutateSuccess,
  onOpenUserDetail,
}: {
  userId: number
  plans: DashRecord[]
  settings?: DashRecord
  isFa: boolean
  /** When true, «free» payment mode is hidden (reseller pays from wallet / invoice only). */
  isReseller?: boolean
  onBack: () => void
  onMutateSuccess?: () => void
  onOpenUserDetail?: (id: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`userDetailAdmin.${k}`, opts)

  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [user, setUser] = useState<DashRecord | null>(null)
  const [services, setServices] = useState<DashRecord[]>([])
  const [referrals, setReferrals] = useState<DashRecord[]>([])
  const [activity, setActivity] = useState<DashRecord[]>([])
  const [actPage, setActPage] = useState(1)
  const [actMeta, setActMeta] = useState<PaginationMeta | null>(null)
  const [busy, setBusy] = useState(false)
  const [alertText, setAlertText] = useState<string | null>(null)

  const [planId, setPlanId] = useState("")
  const [volGb, setVolGb] = useState("")
  const [createMode, setCreateMode] = useState<"free" | "wallet" | "invoice">(() =>
    isReseller ? "wallet" : "free"
  )
  const [walletDialog, setWalletDialog] = useState<null | "add" | "sub">(null)
  const [walletAmount, setWalletAmount] = useState("")
  const [adminMsg, setAdminMsg] = useState("")
  const [adminMsgChannel, setAdminMsgChannel] = useState<"both" | "telegram" | "bale">("both")
  const [actionDlg, setActionDlg] = useState<ServiceActionDlg>(null)
  const [resellerChoices, setResellerChoices] = useState<Array<{ id: number; label: string }>>([])
  const [assignResellerOpen, setAssignResellerOpen] = useState(false)
  const [pickResellerId, setPickResellerId] = useState("")

  const pricePerExtraUser = num(settings?.price_per_extra_user)
  const l2tpEnabled = useMemo(() => {
    const f = settings?.features
    return !!(f && typeof f === "object" && (f as Record<string, unknown>).l2tp === true)
  }, [settings?.features])

  const boot = useMemo(() => window.__SIMPLEVPBOT_DASH__ || {}, [])
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  const nonce = String((boot as { nonce?: string }).nonce || "")

  const load = useCallback(async () => {
    if (!restBase || userId < 1) return
    setLoading(true)
    setErr(null)
    try {
      const sp = new URLSearchParams()
      sp.set("activity_page", String(actPage))
      sp.set("activity_per_page", "20")
      const r = await fetch(`${restBase}/dashboard/admin/user/${userId}?${sp.toString()}`, {
        headers: { "X-WP-Nonce": nonce },
        credentials: "include",
      })
      const json = (await r.json()) as Record<string, unknown>
      if (!r.ok || !json.ok) {
        setErr(String(json.message || "not_found"))
        setUser(null)
        return
      }
      setUser((json.user as DashRecord) || null)
      const rc = json.resellerChoices
      setResellerChoices(
        Array.isArray(rc)
          ? (rc as Array<Record<string, unknown>>)
              .map((r) => ({ id: num(r.id), label: String(r.label ?? "") }))
              .filter((r) => r.id > 0)
          : []
      )
      setServices(Array.isArray(json.services) ? (json.services as DashRecord[]) : [])
      setReferrals(Array.isArray(json.referrals) ? (json.referrals as DashRecord[]) : [])
      setActivity(Array.isArray(json.activity) ? (json.activity as DashRecord[]) : [])
      const pag = json.activityPagination
      setActMeta(parsePaginationMeta(pag))
    } catch {
      setErr(t("userDetailAdmin.loadError"))
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [restBase, nonce, userId, actPage])

  useEffect(() => {
    void load()
  }, [restBase, nonce, userId, actPage, load])

  const activePlans = useMemo(
    () =>
      plans.filter((p) => {
        if (num(p.active) !== 1 || num(p.id) < 1) return false
        if (!l2tpEnabled && String(p.service_type ?? "xray") === "l2tp") return false
        return true
      }),
    [plans, l2tpEnabled]
  )

  const visibleServices = useMemo(
    () =>
      services.filter((s) => l2tpEnabled || String(s.service_type ?? "xray") !== "l2tp"),
    [services, l2tpEnabled]
  )

  const selectedPlan = useMemo(
    () => activePlans.find((p) => String(num(p.id)) === planId),
    [activePlans, planId]
  )

  const createPricePreview = useMemo(
    () => previewCreatePriceToman(selectedPlan, volGb),
    [selectedPlan, volGb]
  )

  const runMut = useCallback(
    async (op: string, params: Record<string, unknown>, okMsg?: string) => {
      setBusy(true)
      setAlertText(null)
      try {
        const res = await postAdminMutate(op, params)
        if (!res.ok) {
          setAlertText(adminMutateErrorText(res, t("userDetailAdmin.mutateError")))
          return
        }
        if (okMsg) setAlertText(okMsg)
        setWalletAmount("")
        await load()
        onMutateSuccess?.()
      } finally {
        setBusy(false)
      }
    },
    [load, onMutateSuccess, t]
  )

  const applyWalletDelta = useCallback(
    (sign: 1 | -1) => {
      const v = parseFloat(walletAmount.replace(/,/g, ".").trim())
      if (!Number.isFinite(v) || v <= 0) return
      void runMut("user_balance_delta", { svp_user_id: num(user?.id), delta: sign * v })
      setWalletDialog(null)
    },
    [runMut, walletAmount, user]
  )

  if (loading && !user) {
    return <p className="text-sm text-muted-foreground">{tp("loading")}</p>
  }
  if (err || !user) {
    return (
      <div className="space-y-3">
        <Button type="button" variant="outline" size="sm" onClick={onBack}>
          {tp("back")}
        </Button>
        <p className="text-sm text-destructive">{err || tp("notFound")}</p>
      </div>
    )
  }

  const uid = num(user.id)
  const st = String(user.status ?? "")
  const bal = num(user.balance)
  const portalUserUrl = String(user.portal_url ?? "")
  const userRole = String(user.role ?? "")
  const invitedBy = num(user.invited_by)
  const invitedByLabel = String(user.invited_by_label ?? "").trim()
  const showAssignReseller = !isReseller && userRole !== "reseller" && resellerChoices.length > 0

  return (
    <TooltipProvider delayDuration={200}>
      <Dialog open={assignResellerOpen} onOpenChange={setAssignResellerOpen}>
        <DialogContent showCloseButton className={cn(isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{tp("assignResellerTitle")}</DialogTitle>
            <DialogDescription>{tp("assignResellerHint")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3 text-sm">
            <p>
              <span className="text-muted-foreground">{tp("currentReseller")}: </span>
              {invitedBy > 0
                ? invitedByLabel || `#${formatPlainLatinInt(invitedBy)}`
                : tp("resellerNone")}
            </p>
            <div className="space-y-2">
              <Label htmlFor="assign-reseller-pick">{tp("assignResellerPick")}</Label>
              <select
                id="assign-reseller-pick"
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                value={pickResellerId}
                onChange={(e) => setPickResellerId(e.target.value)}
                disabled={busy}
              >
                <option value="">{tp("assignResellerPick")}…</option>
                {resellerChoices.map((r) => (
                  <option key={r.id} value={String(r.id)}>
                    {r.label} (#{formatPlainLatinInt(r.id)})
                  </option>
                ))}
              </select>
            </div>
          </div>
          <DialogFooter className={cn("gap-2", isFa && "sm:flex-row-reverse")}>
            {invitedBy > 0 ? (
              <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={async () => {
                  await runMut(
                    "reseller_bind_users",
                    { reseller_svp_user_id: 0, user_ids: [uid], mode: "clear" },
                    tp("assignResellerClear")
                  )
                  setAssignResellerOpen(false)
                }}
              >
                {tp("assignResellerClear")}
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={() => setAssignResellerOpen(false)} disabled={busy}>
              {tp("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              disabled={busy || !pickResellerId}
              onClick={async () => {
                const rid = parseInt(pickResellerId, 10)
                if (!Number.isFinite(rid) || rid < 1) return
                await runMut(
                  "reseller_bind_users",
                  { reseller_svp_user_id: rid, user_ids: [uid], mode: "set" },
                  tp("assignResellerApply")
                )
                setAssignResellerOpen(false)
              }}
            >
              {tp("assignResellerApply")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={walletDialog !== null} onOpenChange={(o) => !o && setWalletDialog(null)}>
        <DialogContent showCloseButton className={cn(isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {walletDialog === "add" ? tp("walletDialogAddTitle") : tp("walletDialogSubTitle")}
            </DialogTitle>
            <DialogDescription>{tp("walletDialogHint")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="w-amt">{tp("walletDialogAmount")}</Label>
            <Input
              id="w-amt"
              dir="ltr"
              className="font-mono"
              value={walletAmount}
              onChange={(e) => setWalletAmount(e.target.value)}
              disabled={busy}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setWalletDialog(null)} disabled={busy}>
              {tp("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              onClick={() => applyWalletDelta(walletDialog === "add" ? 1 : -1)}
              disabled={busy}
            >
              {tp("walletDialogConfirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ServiceActionDialog
        dlg={actionDlg}
        setDlg={setActionDlg}
        isFa={isFa}
        isReseller={isReseller}
        busy={busy}
        plans={plans}
        pricePerExtraUser={pricePerExtraUser}
        tp={tp}
        onConfirm={(op, payload) => void runMut(op, payload)}
      />

      <div className={cn("mx-auto max-w-7xl space-y-6", isFa && "text-right")}>
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="outline" size="sm" onClick={onBack}>
            {tp("back")}
          </Button>
          <h2 className="text-lg font-medium">{tp("title")}</h2>
        </div>

        {alertText ? (
          <div
            role="status"
            className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-foreground"
          >
            {alertText}
          </div>
        ) : null}

        <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
          <Card>
            <CardHeader className="pb-2">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <CardTitle className="text-base">{displayName(user)}</CardTitle>
                  <CardDescription
                    className={cn("flex flex-wrap items-center gap-x-2 gap-y-1 text-xs", isFa && "flex-row-reverse")}
                  >
                    <span className="inline-flex items-center gap-1 font-mono" dir="ltr">
                      <Hash className="size-3.5 shrink-0 opacity-70" aria-hidden />
                      {tp("labelInternalId")} {formatDigits(`#${formatPlainLatinInt(uid)}`, isFa)}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="inline-flex items-center gap-1 font-mono" dir="ltr">
                      <Send className="size-3.5 shrink-0 opacity-70" aria-hidden />
                      {tp("labelTelegram")} {formatDigits(formatPlainLatinInt(num(user.tg_user_id)), isFa)}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="inline-flex items-center gap-1 font-mono" dir="ltr">
                      <Radio className="size-3.5 shrink-0 opacity-70" aria-hidden />
                      {tp("labelBale")} {formatDigits(formatPlainLatinInt(num(user.bale_user_id)), isFa)}
                    </span>
                  </CardDescription>
                </div>
                <Badge variant={statusVariant(st)}>{t(`usersAdmin.status_${st}`, { defaultValue: st })}</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="flex flex-wrap items-center gap-2">
                <Wallet className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <span className="text-muted-foreground">{tp("balance")}:</span>
                <span className="font-medium tabular-nums">{formatNumber(bal, isFa)}</span>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  className="gap-2"
                  disabled={busy}
                  onClick={() => {
                    setWalletAmount("")
                    setWalletDialog("add")
                  }}
                >
                  <Plus className="size-4" aria-hidden />
                  {tp("walletIncrease")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-2"
                  disabled={busy}
                  onClick={() => {
                    setWalletAmount("")
                    setWalletDialog("sub")
                  }}
                >
                  <Minus className="size-4" aria-hidden />
                  {tp("walletDecrease")}
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">{tp("walletDeltaHint")}</p>

              <div>
                <p className="mb-2 text-xs font-medium text-muted-foreground">{tp("adminActions")}</p>
                <div className={cn(actionBar, "justify-start")}>
                  {st === "pending" ? (
                    <>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button
                            type="button"
                            size="icon"
                            variant="default"
                            disabled={busy}
                            aria-label={tp("tooltipApprove")}
                            onClick={() =>
                              void runMut("membership", {
                                membership_user_id: uid,
                                svp_user_membership_action: "approve",
                              })
                            }
                          >
                            <CheckCircle2 className="size-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                          <p>{tp("tooltipApprove")}</p>
                        </TooltipContent>
                      </Tooltip>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            disabled={busy}
                            aria-label={tp("tooltipReject")}
                            onClick={() =>
                              void runMut("membership", {
                                membership_user_id: uid,
                                svp_user_membership_action: "reject",
                              })
                            }
                          >
                            <XCircle className="size-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                          <p>{tp("tooltipReject")}</p>
                        </TooltipContent>
                      </Tooltip>
                    </>
                  ) : null}
                  {st === "rejected" ? (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          type="button"
                          size="icon"
                          variant="secondary"
                          disabled={busy}
                          aria-label={tp("tooltipReopen")}
                          onClick={() =>
                            void runMut("membership", {
                              membership_user_id: uid,
                              svp_user_membership_action: "reopen",
                            })
                          }
                        >
                          <RotateCcw className="size-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{tp("tooltipReopen")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {st !== "blocked" ? (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          type="button"
                          size="icon"
                          variant="destructive"
                          disabled={busy}
                          aria-label={tp("tooltipBan")}
                          onClick={() =>
                            void runMut("user_status", { svp_user_id: uid, user_status_action: "ban" })
                          }
                        >
                          <Ban className="size-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{tp("tooltipBan")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          disabled={busy}
                          aria-label={tp("tooltipUnban")}
                          onClick={() =>
                            void runMut("user_status", { svp_user_id: uid, user_status_action: "unban" })
                          }
                        >
                          <ShieldOff className="size-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{tp("tooltipUnban")}</p>
                      </TooltipContent>
                    </Tooltip>
                  )}
                  {portalUserUrl ? (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <a
                          href={portalUserUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          aria-label={tp("tooltipUserPortal")}
                          className={cn(
                            "inline-flex size-9 items-center justify-center rounded-md border border-input bg-background hover:bg-accent"
                          )}
                        >
                          <ExternalLink className="size-4" />
                        </a>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{tp("tooltipUserPortal")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {showAssignReseller ? (
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          disabled={busy}
                          aria-label={tp("tooltipAssignReseller")}
                          onClick={() => {
                            setPickResellerId(invitedBy > 0 ? String(invitedBy) : "")
                            setAssignResellerOpen(true)
                          }}
                        >
                          <Store className="size-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{tp("tooltipAssignReseller")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                </div>
              </div>

              <div className="space-y-2 rounded-md border border-border/60 bg-muted/20 p-3">
                <Label className="text-xs font-medium">{tp("adminMessageTitle")}</Label>
                <textarea
                  className="min-h-[4rem] w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                  value={adminMsg}
                  onChange={(e) => setAdminMsg(e.target.value)}
                  disabled={busy}
                  placeholder={tp("adminMessagePlaceholder")}
                />
                <div className="flex flex-wrap items-center gap-2">
                  <select
                    className="h-9 rounded-md border border-input bg-background px-2 text-xs"
                    value={adminMsgChannel}
                    onChange={(e) => setAdminMsgChannel(e.target.value as typeof adminMsgChannel)}
                    disabled={busy}
                  >
                    <option value="both">{tp("msgChannelBoth")}</option>
                    <option value="telegram">{tp("msgChannelTelegram")}</option>
                    <option value="bale">{tp("msgChannelBale")}</option>
                  </select>
                  <Button
                    type="button"
                    size="sm"
                    className="gap-2"
                    disabled={busy || !adminMsg.trim()}
                    onClick={() => {
                      void runMut("user_admin_message", {
                        svp_user_id: uid,
                        text: adminMsg.trim(),
                        channel: adminMsgChannel,
                      })
                      setAdminMsg("")
                    }}
                  >
                    <MessageSquare className="size-4" aria-hidden />
                    {tp("adminMessageSend")}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("createService")}</CardTitle>
              <ul className="list-inside list-disc space-y-0.5 text-sm text-muted-foreground">
                {isReseller ? (
                  <>
                    <li>{tp("createServiceHintReseller1")}</li>
                    <li>{tp("createServiceHintReseller2")}</li>
                    <li>{tp("createServiceHintReseller3")}</li>
                  </>
                ) : (
                  <>
                    <li>{tp("createServiceHintShort1")}</li>
                    <li>{tp("createServiceHintShort2")}</li>
                    <li>{tp("createServiceHintShort3")}</li>
                  </>
                )}
              </ul>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
              <div className="space-y-1">
                <Label>{tp("plan")}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                  value={planId}
                  onChange={(e) => setPlanId(e.target.value)}
                  disabled={busy}
                >
                  <option value="">{tp("selectPlan")}</option>
                  {activePlans.map((p) => (
                    <option key={num(p.id)} value={String(p.id)}>
                      #{num(p.id)} — {String(p.label ?? p.name ?? "")}
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-1">
                <Label>{tp("volumeGb")}</Label>
                <Input
                  dir="ltr"
                  value={volGb}
                  onChange={(e) => setVolGb(e.target.value)}
                  placeholder={tp("volumeGbExamplePlaceholder")}
                  disabled={busy}
                />
              </div>
              <div className="space-y-1">
                <Label>{tp("mode")}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                  value={createMode}
                  onChange={(e) => setCreateMode(e.target.value as typeof createMode)}
                  disabled={busy}
                >
                  {!isReseller ? <option value="free">{tp("modeFree")}</option> : null}
                  <option value="wallet">{tp("modeWallet")}</option>
                  <option value="invoice">{tp("modeInvoice")}</option>
                </select>
              </div>
                    <Button
                      type="button"
                      size="sm"
                className="gap-2 lg:self-end"
                      disabled={busy || !planId}
                      onClick={() => {
                        const pid = parseInt(planId, 10)
                        const v = volGb.trim() ? parseInt(volGb, 10) : NaN
                        void runMut("user_create_service", {
                          target_user_id: uid,
                          plan_id: pid,
                          volume_gb: Number.isFinite(v) ? v : null,
                          mode: createMode,
                        })
                      }}
                    >
                      <PackagePlus className="size-4" />
                      {tp("create")}
                    </Button>
              </div>
              {createPricePreview != null ? (
                <p className="text-xs text-muted-foreground">
                  {tp("estimatedCost")}:{" "}
                  <span className="font-medium text-foreground tabular-nums">
                    {formatNumber(createPricePreview, isFa)}
                  </span>
                </p>
              ) : selectedPlan && isPerGbPlan(selectedPlan) ? (
                <p className="text-xs text-muted-foreground">{tp("estimatedCostNeedVolume")}</p>
              ) : null}
            </CardContent>
          </Card>
        </div>

        {referrals.length > 0 ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("referralsTitle")}</CardTitle>
              <CardDescription>{tp("referralsHint")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {referrals.map((ref) => {
                const rid = num(ref.id)
                return (
                  <div
                    key={rid}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-2 py-1.5"
                  >
                    <span>
                      #{formatPlainLatinInt(rid)} — {displayName(ref)}
                    </span>
                    {onOpenUserDetail ? (
                      <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(rid)}>
                        {tp("referralsManage")}
                      </Button>
                    ) : null}
                  </div>
                )
              })}
            </CardContent>
          </Card>
        ) : null}

        <div className="space-y-4">
          <h3 className="text-base font-medium">{tp("services")}</h3>
          {visibleServices.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("noServices")}</p>
          ) : (
            <div className="grid gap-4 md:grid-cols-2">
              {visibleServices.map((svc) => {
                const sid = num(svc.id)
                const patchAlert = (
                  key: "alerts_enabled" | "alerts_volume" | "alerts_expiry" | "alerts_users",
                  val: boolean
                ) => {
                  void runMut("service_alerts_patch", { service_id: sid, [key]: val ? 1 : 0 })
                }
                return (
                  <DashboardUserServiceCard
                    key={sid}
                    svc={svc}
                    plans={plans}
                    isFa={isFa}
                    isReseller={isReseller}
                    busy={busy}
                    tp={tp}
                    onOpenAction={(kind, s) => setActionDlg({ kind, sid: num(s.id), svc: s })}
                    onPatchAlert={patchAlert}
                  />
                )
              })}
            </div>
          )}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("activity")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="w-full overflow-x-auto rounded-md border border-border">
              <table
                className={cn(
                  "w-full min-w-[20rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border",
                  isFa ? "text-right" : "text-left"
                )}
              >
                <thead>
                  <tr className="bg-muted/40">
                    <th className="p-2">{tp("colTime")}</th>
                    <th className="p-2">{tp("colChannel")}</th>
                    <th className="p-2">{tp("colSummary")}</th>
                  </tr>
                </thead>
                <tbody>
                  {activity.map((row) => {
                    const id = num(row.id)
                    return (
                      <tr key={id}>
                        <td className="p-2 whitespace-nowrap">{formatDateTime(String(row.created_at ?? ""), isFa)}</td>
                        <td className="p-2">{String(row.channel ?? "")}</td>
                        <td className="p-2 text-foreground">{formatUserActivityLine(row, t)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <DataPagination
              meta={actMeta}
              isFa={isFa}
              onPageChange={(p) => setActPage(p)}
              onPerPageChange={() => {
                /* fixed 20 via API */
              }}
            />
          </CardContent>
        </Card>
      </div>
    </TooltipProvider>
  )
}

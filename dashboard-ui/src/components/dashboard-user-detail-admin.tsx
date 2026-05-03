"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  Ban,
  CheckCircle2,
  HardDrive,
  Link2,
  Loader2,
  PackagePlus,
  Plus,
  RefreshCw,
  RotateCcw,
  Send,
  ShieldOff,
  Trash2,
  UserPlus,
  Wallet,
  XCircle,
} from "lucide-react"

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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber, formatPlainLatinInt } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { parsePaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

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

const glassBar =
  "flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/30 p-2.5 backdrop-blur-sm"

export function DashboardUserDetailAdmin({
  userId,
  plans,
  isFa,
  onBack,
  onMutateSuccess,
}: {
  userId: number
  plans: DashRecord[]
  isFa: boolean
  onBack: () => void
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`userDetailAdmin.${k}`)

  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [user, setUser] = useState<DashRecord | null>(null)
  const [services, setServices] = useState<DashRecord[]>([])
  const [activity, setActivity] = useState<DashRecord[]>([])
  const [actPage, setActPage] = useState(1)
  const [actMeta, setActMeta] = useState<PaginationMeta | null>(null)
  const [busy, setBusy] = useState(false)
  const [alertText, setAlertText] = useState<string | null>(null)

  const [wpLink, setWpLink] = useState("")
  const [planId, setPlanId] = useState("")
  const [volGb, setVolGb] = useState("")
  const [createMode, setCreateMode] = useState<"free" | "wallet" | "invoice">("free")
  const [xferTarget, setXferTarget] = useState<Record<number, string>>({})
  const [renewMode, setRenewMode] = useState<Record<number, "free" | "wallet" | "invoice">>({})
  const [addVolGb, setAddVolGb] = useState<Record<number, string>>({})
  const [addVolMode, setAddVolMode] = useState<Record<number, "free" | "wallet" | "invoice">>({})
  const [balanceDelta, setBalanceDelta] = useState("")

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
      setServices(Array.isArray(json.services) ? (json.services as DashRecord[]) : [])
      setActivity(Array.isArray(json.activity) ? (json.activity as DashRecord[]) : [])
      const pag = json.activityPagination
      setActMeta(parsePaginationMeta(pag))
    } catch {
      setErr(tp("loadError"))
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [restBase, nonce, userId, actPage, tp])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!user) return
    const w = num(user.wp_user_id)
    setWpLink(w > 0 ? String(w) : "")
  }, [user])

  const activePlans = useMemo(
    () => plans.filter((p) => num(p.active) === 1 && num(p.id) > 0),
    [plans]
  )

  const runMut = useCallback(
    async (op: string, params: Record<string, unknown>, okMsg?: string) => {
      setBusy(true)
      setAlertText(null)
      try {
        const res = await postAdminMutate(op, params)
        if (!res.ok) {
          const parts = [res.message, res.reason].filter(Boolean)
          setAlertText(parts.length ? parts.join(" — ") : tp("mutateError"))
          return
        }
        if (okMsg) setAlertText(okMsg)
        setBalanceDelta("")
        await load()
        onMutateSuccess?.()
      } finally {
        setBusy(false)
      }
    },
    [load, onMutateSuccess, tp]
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
  const wpUid = num(user.wp_user_id)

  return (
    <TooltipProvider delayDuration={200}>
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
                  <CardDescription dir="ltr" className="font-mono text-xs">
                    #{formatPlainLatinInt(uid)} · TG {formatPlainLatinInt(num(user.tg_user_id))} · Bale{" "}
                    {formatPlainLatinInt(num(user.bale_user_id))}
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

              <div className="space-y-2">
                <Label htmlFor="bal-delta">{tp("walletAdjust")}</Label>
                <div className="flex flex-wrap items-end gap-2">
                  <Input
                    id="bal-delta"
                    dir="ltr"
                    className="max-w-[12rem] font-mono"
                    placeholder="±0"
                    value={balanceDelta}
                    onChange={(e) => setBalanceDelta(e.target.value)}
                    disabled={busy}
                  />
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        disabled={busy}
                        aria-label={tp("applyDelta")}
                        onClick={() => {
                          const v = parseFloat(balanceDelta.replace(/,/g, ".").trim())
                          if (!Number.isFinite(v) || v === 0) return
                          void runMut("user_balance_delta", { svp_user_id: uid, delta: v })
                        }}
                      >
                        {busy ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                      </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                      <p>{tp("applyDelta")}</p>
                    </TooltipContent>
                  </Tooltip>
                </div>
                <p className="text-xs text-muted-foreground">{tp("walletDeltaHint")}</p>
              </div>

              <div>
                <p className="mb-2 text-xs font-medium text-muted-foreground">{tp("adminActions")}</p>
                <div className={cn(glassBar, "justify-start")}>
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
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("createService")}</CardTitle>
              <CardDescription>{tp("createServiceHint")}</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1 sm:col-span-2">
                <Label>{tp("plan")}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                  value={planId}
                  onChange={(e) => setPlanId(e.target.value)}
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
                  placeholder="20"
                />
              </div>
              <div className="space-y-1">
                <Label>{tp("mode")}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                  value={createMode}
                  onChange={(e) => setCreateMode(e.target.value as typeof createMode)}
                >
                  <option value="free">{tp("modeFree")}</option>
                  <option value="wallet">{tp("modeWallet")}</option>
                  <option value="invoice">{tp("modeInvoice")}</option>
                </select>
              </div>
              <div className="flex items-end sm:col-span-2">
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      type="button"
                      size="sm"
                      className="gap-2"
                      disabled={busy || !planId}
                      aria-label={tp("tooltipCreateService")}
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
                  </TooltipTrigger>
                  <TooltipContent>
                    <p>{tp("tooltipCreateService")}</p>
                  </TooltipContent>
                </Tooltip>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Link2 className="size-4 opacity-70" aria-hidden />
              {tp("linkWp")}
            </CardTitle>
            <CardDescription>{tp("linkWpHint")}</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap items-end gap-2">
            <div className="space-y-1">
              <Label htmlFor="wpuid">{tp("wpUserId")}</Label>
              <Input
                id="wpuid"
                dir="ltr"
                className="w-40 font-mono"
                placeholder={wpUid > 0 ? String(wpUid) : "0"}
                value={wpLink}
                onChange={(e) => setWpLink(e.target.value)}
              />
            </div>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  size="sm"
                  disabled={busy}
                  className="gap-2"
                  aria-label={tp("tooltipSaveWp")}
                  onClick={() => {
                    const v = parseInt(wpLink.trim(), 10)
                    void runMut("link_wp_user", {
                      svp_user_id: uid,
                      wp_user_id: Number.isFinite(v) ? v : 0,
                    })
                  }}
                >
                  <UserPlus className="size-4" />
                  {tp("saveLink")}
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                <p>{tp("tooltipSaveWp")}</p>
              </TooltipContent>
            </Tooltip>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <h3 className="text-base font-medium">{tp("services")}</h3>
          {services.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("noServices")}</p>
          ) : (
            <div className="grid gap-4 md:grid-cols-2">
              {services.map((svc) => {
                const sid = num(svc.id)
                const rm = renewMode[sid] ?? "free"
                const am = addVolMode[sid] ?? "free"
                const expire =
                  svc.expires_at ?? svc.expire_at ?? svc.expired_at ?? svc.expiry ?? ""
                return (
                  <Card key={sid} className="overflow-hidden" dir={isFa ? "rtl" : "ltr"}>
                    <CardHeader className="pb-2">
                      <CardTitle dir="ltr" className="font-mono text-sm">
                        #{formatPlainLatinInt(sid)}
                      </CardTitle>
                      <CardDescription className="break-all text-xs">
                        {String(svc.email ?? "—")}
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                      <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                        <div>
                          <span className="text-muted-foreground">{tp("svcStatus")}: </span>
                          {String(svc.status ?? "—")}
                        </div>
                        <div dir="ltr">
                          <span className="text-muted-foreground">{tp("svcPlan")}: </span>
                          {formatPlainLatinInt(num(svc.plan_id))}
                        </div>
                        <div dir="ltr">
                          <span className="text-muted-foreground">{tp("svcVolume")}: </span>
                          {String(svc.volume_gb ?? svc.volume ?? "—")}
                        </div>
                        <div dir="ltr" className="col-span-2">
                          <span className="text-muted-foreground">{tp("svcExpires")}: </span>
                          {String(expire || "—")}
                        </div>
                      </div>

                      <div>
                        <p className="mb-1.5 text-xs font-medium text-muted-foreground">{tp("serviceActions")}</p>
                        <div className={glassBar}>
                          <select
                            className="h-9 max-w-[7rem] rounded-md border border-input bg-background px-2 text-xs"
                            value={rm}
                            onChange={(e) =>
                              setRenewMode((m) => ({ ...m, [sid]: e.target.value as typeof rm }))
                            }
                          >
                            <option value="free">{tp("modeFree")}</option>
                            <option value="wallet">{tp("modeWallet")}</option>
                            <option value="invoice">{tp("modeInvoice")}</option>
                          </select>
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                size="icon"
                                variant="secondary"
                                className="shrink-0"
                                disabled={busy}
                                aria-label={tp("tooltipRenew")}
                                onClick={() => void runMut("user_renew_service", { service_id: sid, mode: rm })}
                              >
                                <RefreshCw className="size-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>{tp("tooltipRenew")}</p>
                            </TooltipContent>
                          </Tooltip>

                          <Input
                            dir="ltr"
                            className="h-9 w-16 text-xs"
                            placeholder="GB"
                            value={addVolGb[sid] ?? ""}
                            onChange={(e) => setAddVolGb((g) => ({ ...g, [sid]: e.target.value }))}
                          />
                          <select
                            className="h-9 max-w-[7rem] rounded-md border border-input bg-background px-2 text-xs"
                            value={am}
                            onChange={(e) =>
                              setAddVolMode((m) => ({ ...m, [sid]: e.target.value as typeof am }))
                            }
                          >
                            <option value="free">{tp("modeFree")}</option>
                            <option value="wallet">{tp("modeWallet")}</option>
                            <option value="invoice">{tp("modeInvoice")}</option>
                          </select>
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="shrink-0"
                                disabled={busy}
                                aria-label={tp("tooltipAddVolume")}
                                onClick={() => {
                                  const g = parseInt(addVolGb[sid] ?? "", 10)
                                  if (!Number.isFinite(g) || g < 1) return
                                  void runMut("user_add_volume", { service_id: sid, extra_gb: g, mode: am })
                                }}
                              >
                                <HardDrive className="size-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>{tp("tooltipAddVolume")}</p>
                            </TooltipContent>
                          </Tooltip>

                          <Input
                            dir="ltr"
                            className="h-9 min-w-0 flex-1 font-mono text-xs"
                            placeholder={tp("transferPlaceholder")}
                            value={xferTarget[sid] ?? ""}
                            onChange={(e) => setXferTarget((x) => ({ ...x, [sid]: e.target.value }))}
                          />
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="shrink-0"
                                disabled={busy}
                                aria-label={tp("tooltipTransfer")}
                                onClick={() => {
                                  const tgt = (xferTarget[sid] ?? "").trim()
                                  if (!tgt) return
                                  void runMut("user_service_transfer", { service_id: sid, target: tgt })
                                }}
                              >
                                <Send className="size-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>{tp("tooltipTransfer")}</p>
                            </TooltipContent>
                          </Tooltip>

                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                size="icon"
                                variant="destructive"
                                className="shrink-0"
                                disabled={busy}
                                aria-label={tp("tooltipDeleteService")}
                                onClick={() => {
                                  if (!window.confirm(tp("confirmDeleteService"))) return
                                  void runMut("service_delete", { service_id: sid })
                                }}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                              <p>{tp("tooltipDeleteService")}</p>
                            </TooltipContent>
                          </Tooltip>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
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
                  "w-full min-w-[28rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border",
                  isFa ? "text-right" : "text-left"
                )}
              >
                <thead>
                  <tr className="bg-muted/40">
                    <th className="p-2">id</th>
                    <th className="p-2">{tp("colTime")}</th>
                    <th className="p-2">{tp("colChannel")}</th>
                    <th className="p-2">{tp("colEvent")}</th>
                    <th className="p-2">{tp("colPayload")}</th>
                  </tr>
                </thead>
                <tbody>
                  {activity.map((row) => {
                    const id = num(row.id)
                    const pl = row.payload
                    const preview =
                      pl && typeof pl === "object"
                        ? JSON.stringify(pl as Record<string, unknown>).slice(0, 160)
                        : String(pl ?? "")
                    return (
                      <tr key={id}>
                        <td dir="ltr" className="p-2 font-mono tabular-nums">
                          {formatPlainLatinInt(id)}
                        </td>
                        <td className="p-2 whitespace-nowrap">{String(row.created_at ?? "")}</td>
                        <td className="p-2">{String(row.channel ?? "")}</td>
                        <td className="p-2 font-mono">{String(row.event_type ?? "")}</td>
                        <td className="max-w-[24rem] break-all p-2 text-muted-foreground">{preview}</td>
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

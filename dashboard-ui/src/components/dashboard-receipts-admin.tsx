"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"

import { DashboardDateTimePicker } from "@/components/dashboard-datetime-picker"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
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
import { DataPagination } from "@/components/data-pagination"
import {
  adminMutateErrorText,
  postAdminMutate,
  type AdminMutateResult,
} from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

export type ReceiptAggregateRow = {
  status: string
  count: number
  sumAmount: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parseAggregates(raw: unknown): ReceiptAggregateRow[] {
  if (!Array.isArray(raw)) return []
  const out: ReceiptAggregateRow[] = []
  for (const row of raw) {
    if (!row || typeof row !== "object") continue
    const r = row as Record<string, unknown>
    out.push({
      status: String(r.status ?? ""),
      count: num(r.count),
      sumAmount: num(r.sumAmount ?? r.sum_amount),
    })
  }
  return out
}

function receiptStatusVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "rejected") return "destructive"
  return "secondary"
}

function receiptStatusLabel(st: string, tp: (k: string) => string): string {
  if (st === "pending") return tp("statusPending")
  if (st === "processing") return tp("statusProcessing")
  if (st === "approved") return tp("statusApproved")
  if (st === "rejected") return tp("statusRejected")
  return st || "—"
}

function formatReceiptAmount(amount: number, isFa: boolean, tp: (k: string) => string): string {
  if (Math.abs(amount) < 0.009) {
    return tp("amountFree")
  }
  return formatNumber(amount, isFa)
}

function formatReceiptMutateFeedback(res: AdminMutateResult, tp: (k: string) => string): string | null {
  const d = res.data
  if (!res.ok) {
    const raw = adminMutateErrorText(res, tp("mutateError"))
    const detail = raw === "bad_amount" ? tp("badAmount") : raw
    return `${tp("mutateError")}: ${detail}`
  }
  const msgKey = typeof res.message === "string" ? res.message : ""
  const msgMap: Record<string, string> = {
    topup_delta_applied: tp("amountTopupAdjusted"),
    amount_updated: tp("amountUpdated"),
    amount_unchanged: tp("amountUnchanged"),
    commission_may_need_manual_review: tp("commissionReviewWarning"),
  }
  if (msgKey && msgMap[msgKey]) {
    const warnings = (res as { warnings?: unknown }).warnings
    if (Array.isArray(warnings) && warnings.includes("commission_may_need_manual_review")) {
      return `${msgMap[msgKey]} ${tp("commissionReviewWarning")}`
    }
    return msgMap[msgKey]
  }
  if (d && typeof d === "object" && "ok" in d && (d as { ok?: unknown }).ok === false) {
    const rec = d as Record<string, unknown>
    const detail =
      typeof rec.provision_error === "string" && rec.provision_error
        ? rec.provision_error
        : typeof rec.message === "string" && rec.message
          ? rec.message
          : typeof rec.reason === "string"
            ? rec.reason
            : JSON.stringify(d)
    return `${tp("approveFailed")}: ${detail}`
  }
  return null
}

function parseRejectReasons(settings: DashRecord | undefined): string[] {
  const raw = settings?.receipt_reject_reasons
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x ?? "").trim()).filter(Boolean)
  }
  if (typeof raw === "string" && raw.trim()) {
    try {
      const j = JSON.parse(raw) as unknown
      if (Array.isArray(j)) return j.map((x) => String(x ?? "").trim()).filter(Boolean)
    } catch {
      return raw.split(/\r?\n/).map((x) => x.trim()).filter(Boolean)
    }
  }
  return []
}

export type ReceiptsListFilters = {
  q: string
  status: string
  sort: string
  dateFrom: string
  dateTo: string
  amountMin: string
  amountMax: string
}

function receiptUserLabel(r: DashRecord): string {
  const label = String(r.user_label ?? "").trim()
  if (label) return label
  const name = String(r.user_name ?? "").trim()
  if (name) return name
  const username = String(r.username ?? "").trim()
  if (username) return username.startsWith("@") ? username : `@${username}`
  return `#${String(r.user_id ?? "—")}`
}

export function DashboardReceiptsAdmin({
  receipts,
  receiptAggregates,
  settings,
  pagination,
  isFa,
  isReseller = false,
  canReviewReceipts = true,
  actorBalance,
  customerCharges = [],
  listFilters,
  onListFiltersChange,
  dashboardBaseUrl = "",
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  receipts: DashRecord[]
  receiptAggregates?: unknown
  settings?: DashRecord
  pagination: PaginationMeta | null
  isFa: boolean
  isReseller?: boolean
  canReviewReceipts?: boolean
  actorBalance?: number
  customerCharges?: DashRecord[]
  listFilters: ReceiptsListFilters
  onListFiltersChange: (patch: Partial<ReceiptsListFilters>) => void
  dashboardBaseUrl?: string
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`receiptsAdmin.${k}`)
  const tw = (k: string, opts?: Record<string, string | number>) => t(`resellerFinance.${k}`, opts)

  const showWalletSection =
    isReseller || typeof actorBalance === "number" || customerCharges.length > 0

  const showFullReviewUi = canReviewReceipts

  const aggregates = useMemo(() => parseAggregates(receiptAggregates), [receiptAggregates])

  const aggByStatus = useMemo(() => {
    const m = new Map<string, { count: number; sum: number }>()
    let totalCount = 0
    let totalSum = 0
    for (const a of aggregates) {
      totalCount += a.count
      totalSum += a.sumAmount
      m.set(a.status, { count: a.count, sum: a.sumAmount })
    }
    return { m, totalCount, totalSum }
  }, [aggregates])

  const approved = aggByStatus.m.get("approved") ?? { count: 0, sum: 0 }
  const pendingRaw = aggByStatus.m.get("pending") ?? { count: 0, sum: 0 }
  const processingRaw = aggByStatus.m.get("processing") ?? { count: 0, sum: 0 }
  const pending = {
    count: pendingRaw.count + processingRaw.count,
    sum: pendingRaw.sum + processingRaw.sum,
  }
  const rejected = aggByStatus.m.get("rejected") ?? { count: 0, sum: 0 }

  const approvedReceipts = useMemo(() => {
    return receipts.filter((r) => String(r.status ?? "").toLowerCase() === "approved")
  }, [receipts])

  const [searchDraft, setSearchDraft] = useState(listFilters.q)
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [alertText, setAlertText] = useState<string | null>(null)
  const [topUpAmount, setTopUpAmount] = useState("")
  const [topUpBusy, setTopUpBusy] = useState(false)
  const [topUpMsg, setTopUpMsg] = useState<string | null>(null)
  const [amountTarget, setAmountTarget] = useState<DashRecord | null>(null)
  const [amountDraft, setAmountDraft] = useState("")
  const [rejectTarget, setRejectTarget] = useState<DashRecord | null>(null)
  const [rejectReason, setRejectReason] = useState("")
  const [rejectCustomReason, setRejectCustomReason] = useState("")
  const [previewReceipt, setPreviewReceipt] = useState<DashRecord | null>(null)

  const rejectReasons = useMemo(() => parseRejectReasons(settings), [settings])
  const settingsUrl = `${dashboardBaseUrl.replace(/\/?$/, "")}/site_settings/#whitelabel`

  useEffect(() => {
    setSearchDraft(listFilters.q)
  }, [listFilters.q])

  useEffect(() => {
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current)
    searchDebounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      if (next !== listFilters.q.trim()) {
        onListFiltersChange({ q: next })
      }
    }, 500)
    return () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current)
    }
  }, [searchDraft, listFilters.q, onListFiltersChange])

  const updateReceipt = useCallback(
    async (receiptId: number, payload: Record<string, unknown>) => {
      setBusyId(receiptId)
      setAlertText(null)
      try {
        const res = await postAdminMutate("receipt_update", {
          receipt_id: receiptId,
          ...payload,
        })
        const fb = formatReceiptMutateFeedback(res, tp)
        if (fb) setAlertText(fb)
        else onMutateSuccess?.()
      } finally {
        setBusyId(null)
      }
    },
    [onMutateSuccess, tp]
  )

  const openAmountDialog = (r: DashRecord) => {
    setAmountTarget(r)
    setAmountDraft(String(r.amount ?? ""))
  }

  const saveAmount = async () => {
    if (!amountTarget) return
    const id = num(amountTarget.id)
    await updateReceipt(id, { amount: amountDraft })
    setAmountTarget(null)
  }

  const openRejectDialog = (r: DashRecord) => {
    setRejectTarget(r)
    setRejectReason(rejectReasons[0] ?? "")
    setRejectCustomReason("")
  }

  const confirmReject = async () => {
    if (!rejectTarget) return
    const reason = rejectCustomReason.trim() || rejectReason.trim()
    await updateReceipt(num(rejectTarget.id), { status: "rejected", reject_reason: reason })
    setRejectTarget(null)
  }

  const onTopUp = async () => {
    const raw = topUpAmount.replace(/,/g, ".").trim()
    const amt = parseFloat(raw)
    if (!Number.isFinite(amt) || amt <= 0) {
      setTopUpMsg(tw("topUpInvalid"))
      return
    }
    setTopUpBusy(true)
    setTopUpMsg(null)
    try {
      const res = await postAdminMutate("reseller_wallet_topup_checkout", { amount: amt })
      if (!res.ok) {
        setTopUpMsg(res.message || res.reason || tw("topUpError"))
        return
      }
      const tid = (res as { transaction_id?: number }).transaction_id
      const botHint = (res as { notify_sent?: boolean }).notify_sent ? tw("topUpSentBot") : tw("topUpNoBot")
      setTopUpMsg(
        tw("topUpQueued", {
          id: tid != null && tid > 0 ? formatNumber(tid, isFa) : "—",
          bot: botHint,
        })
      )
      setTopUpAmount("")
      onMutateSuccess?.()
    } finally {
      setTopUpBusy(false)
    }
  }

  const selectClass =
    "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

  return (
    <div className={dashPageRootClass(isFa)} dir={dashDir(isFa)}>
      <DashboardPageHeader
        title={tp("title")}
        description={showWalletSection && isReseller ? tw("subtitle") : tp("subtitle")}
      />

      {alertText ? (
        <div
          role="alert"
          className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100"
        >
          {alertText}
        </div>
      ) : null}

      {showWalletSection ? (
        <div className="space-y-4">
          {typeof actorBalance === "number" ? (
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base">{tw("balanceTitle")}</CardTitle>
                <CardDescription>{tw("balanceHint")}</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-3xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
              </CardContent>
            </Card>
          ) : null}

          {customerCharges.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{tw("customerChargesTitle")}</CardTitle>
                <CardDescription>{tw("customerChargesHint")}</CardDescription>
              </CardHeader>
              <CardContent>
                <ul className="space-y-2">
                  {customerCharges.map((row) => {
                    const id = num(row.id)
                    const amt = num(row.amount)
                    const label = String(row.customer_label ?? "")
                    return (
                      <li
                        key={id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                      >
                        <span className="font-medium tabular-nums text-destructive">
                          {t("resellerCharge.customerChargeLine", {
                            amount: formatNumber(amt, isFa),
                            name: label || `#${num(row.customer_svp_user_id)}`,
                          })}
                        </span>
                        <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                      </li>
                    )
                  })}
                </ul>
              </CardContent>
            </Card>
          ) : null}

          {isReseller ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{tw("topUpTitle")}</CardTitle>
                <CardDescription>{tw("topUpHint")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                {topUpMsg ? (
                  <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">{topUpMsg}</p>
                ) : null}
                <div className="flex flex-wrap items-end gap-3">
                  <div className="space-y-2">
                    <Label htmlFor="topup-amt">{tw("topUpAmount")}</Label>
                    <Input
                      id="topup-amt"
                      dir="ltr"
                      inputMode="decimal"
                      value={topUpAmount}
                      onChange={(e) => setTopUpAmount(e.target.value)}
                      placeholder={tw("topUpPlaceholder")}
                    />
                  </div>
                  <Button type="button" disabled={topUpBusy} onClick={() => void onTopUp()}>
                    {topUpBusy ? "…" : tw("topUpSubmit")}
                  </Button>
                </div>
              </CardContent>
            </Card>
          ) : null}
        </div>
      ) : null}

      {showFullReviewUi ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>{tp("statTotalCount")}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{formatNumber(aggByStatus.totalCount, isFa)}</CardTitle>
              </CardHeader>
              <CardContent className="text-xs text-muted-foreground">
                {tp("statTotalSum")}: {formatNumber(aggByStatus.totalSum, isFa)}
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>{tp("statApprovedIncome")}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{formatNumber(approved.sum, isFa)}</CardTitle>
              </CardHeader>
              <CardContent className="text-xs text-muted-foreground">
                {tp("statCount")}: {formatNumber(approved.count, isFa)}
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>{tp("statPending")}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{formatNumber(pending.count, isFa)}</CardTitle>
              </CardHeader>
              <CardContent className="text-xs text-muted-foreground">
                {tp("statPendingSum")}: {formatNumber(pending.sum, isFa)}
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="pb-2">
                <CardDescription>{tp("statRejected")}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{formatNumber(rejected.count, isFa)}</CardTitle>
              </CardHeader>
              <CardContent className="text-xs text-muted-foreground">
                {tp("statRejectedSum")}: {formatNumber(rejected.sum, isFa)}
              </CardContent>
            </Card>
          </div>

          {rejectReasons.length === 0 && !isReseller ? (
            <p className="text-xs text-muted-foreground">
              {tp("settingsRejectHint")}{" "}
              {settingsUrl ? (
                <a href={settingsUrl} className="text-primary underline-offset-2 hover:underline">
                  {tp("settingsRejectLink")}
                </a>
              ) : null}
            </p>
          ) : null}

          <div className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3">
            <div className="space-y-2">
              <Label htmlFor="rcpt-search">{tp("searchPlaceholder")}</Label>
              <Input
                id="rcpt-search"
                value={searchDraft}
                onChange={(e) => setSearchDraft(e.target.value)}
                placeholder={tp("searchPlaceholder")}
              />
            </div>
            <div className="flex flex-wrap items-end gap-3">
              <div className="space-y-2">
                <Label className="text-xs text-muted-foreground">{tp("filterStatus")}</Label>
                <select
                  className={selectClass + " w-auto min-w-[10rem]"}
                  value={listFilters.status}
                  onChange={(e) => onListFiltersChange({ status: e.target.value })}
                >
                  <option value="all">{tp("filterAll")}</option>
                  <option value="pending">{tp("statusPending")}</option>
                  <option value="processing">{tp("statusProcessing")}</option>
                  <option value="approved">{tp("statusApproved")}</option>
                  <option value="rejected">{tp("statusRejected")}</option>
                </select>
              </div>
              <div className="space-y-2">
                <Label className="text-xs text-muted-foreground">{tp("sortLabel")}</Label>
                <select
                  className={selectClass + " w-auto min-w-[10rem]"}
                  value={listFilters.sort}
                  onChange={(e) => onListFiltersChange({ sort: e.target.value })}
                >
                  <option value="created_desc">{tp("sortCreatedDesc")}</option>
                  <option value="created_asc">{tp("sortCreatedAsc")}</option>
                  <option value="amount_desc">{tp("sortAmountDesc")}</option>
                  <option value="amount_asc">{tp("sortAmountAsc")}</option>
                  <option value="id_desc">{tp("sortIdDesc")}</option>
                </select>
              </div>
              <div className="min-w-[11rem] flex-1">
                <DashboardDateTimePicker
                  label={tp("dateFrom")}
                  isFa={isFa}
                  value={listFilters.dateFrom}
                  onChange={(v) => onListFiltersChange({ dateFrom: v })}
                />
              </div>
              <div className="min-w-[11rem] flex-1">
                <DashboardDateTimePicker
                  label={tp("dateTo")}
                  isFa={isFa}
                  value={listFilters.dateTo}
                  onChange={(v) => onListFiltersChange({ dateTo: v })}
                />
              </div>
              <div className="space-y-2">
                <Label className="text-xs text-muted-foreground">{tp("amountMin")}</Label>
                <Input
                  dir="ltr"
                  className="w-28 font-mono"
                  value={listFilters.amountMin}
                  onChange={(e) => onListFiltersChange({ amountMin: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label className="text-xs text-muted-foreground">{tp("amountMax")}</Label>
                <Input
                  dir="ltr"
                  className="w-28 font-mono"
                  value={listFilters.amountMax}
                  onChange={(e) => onListFiltersChange({ amountMax: e.target.value })}
                />
              </div>
            </div>
          </div>

          <p className="text-xs text-muted-foreground">
            {pagination
              ? t("receiptsAdmin.listPaginationHint", { total: formatNumber(pagination.total, isFa) })
              : t("receiptsAdmin.sampleHint", { n: receipts.length })}
          </p>

          <Separator />

          {receipts.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("emptyList")}</p>
          ) : (
            <Card>
              <CardContent className="overflow-x-auto p-0">
                <table
                  className="w-full min-w-[920px] text-sm"
                  dir={dashDir(isFa)}
                >
                  <thead>
                    <tr className="border-b bg-muted/40 text-muted-foreground">
                      <th className="px-3 py-2 text-start">{tp("colReceipt")}</th>
                      <th className="px-3 py-2 text-start">{tp("colUserName")}</th>
                      <th className="px-3 py-2 text-start">{tp("colUserId")}</th>
                      <th className="px-3 py-2 text-start">{tp("colAmount")}</th>
                      <th className="px-3 py-2 text-start">{tp("colCreated")}</th>
                      <th className="px-3 py-2 text-start">{tp("colStatus")}</th>
                      <th className="px-3 py-2 text-start">{tp("colActions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {receipts.map((r) => {
                      const id = num(r.id)
                      const st = String(r.status ?? "")
                      const imageUrl = String(r.imageUrl ?? "").trim()
                      const isApproved = st === "approved"
                      return (
                        <tr key={id} className="border-b border-border/70 align-top">
                          <td className="px-3 py-2">
                            <div className="flex items-center gap-2">
                              {imageUrl ? (
                                <button
                                  type="button"
                                  className="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-border bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                  title={tp("clickToEnlarge")}
                                  onClick={() => setPreviewReceipt(r)}
                                >
                                  <img
                                    src={imageUrl}
                                    alt={`${tp("receiptImage")} #${formatNumber(id, isFa)}`}
                                    className="h-full w-full cursor-pointer object-cover"
                                    loading="lazy"
                                  />
                                </button>
                              ) : (
                                <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-dashed border-border bg-muted/20 px-1 text-center text-[10px] leading-tight text-muted-foreground">
                                  {tp("noImage")}
                                </span>
                              )}
                              <span className="font-mono text-sm">#{formatNumber(id, isFa)}</span>
                            </div>
                          </td>
                          <td className="px-3 py-2">
                            <div className="font-medium">{receiptUserLabel(r)}</div>
                            {String(r.username ?? "").trim() ? (
                              <div className="text-xs text-muted-foreground">@{String(r.username).replace(/^@/, "")}</div>
                            ) : null}
                          </td>
                          <td className="px-3 py-2 tabular-nums">#{formatNumber(num(r.user_id), isFa)}</td>
                          <td className="px-3 py-2">
                            <div className="font-medium tabular-nums">
                              {formatReceiptAmount(num(r.amount), isFa, tp)}
                            </div>
                            {num(r.transaction_amount) !== num(r.amount) ? (
                              <div className="text-xs text-muted-foreground">
                                {tp("txAmount")}: {formatReceiptAmount(num(r.transaction_amount), isFa, tp)}
                              </div>
                            ) : null}
                          </td>
                          <td className="px-3 py-2">{formatDateTime(r.created_at as string | undefined, isFa)}</td>
                          <td className="px-3 py-2">
                            <Badge variant={receiptStatusVariant(st)}>{receiptStatusLabel(st, tp)}</Badge>
                            <select
                              className={cn(selectClass, "mt-2 h-8 min-w-28")}
                              value={st === "processing" ? "pending" : st}
                              disabled={busyId === id}
                              onChange={(e) => {
                                const next = e.target.value
                                if (next === "rejected") openRejectDialog(r)
                                else void updateReceipt(id, { status: next })
                              }}
                            >
                              <option value="pending">{tp("statusPending")}</option>
                              {st === "processing" ? <option value="processing">{tp("statusProcessing")}</option> : null}
                              <option value="approved">{tp("statusApproved")}</option>
                              <option value="rejected">{tp("statusRejected")}</option>
                            </select>
                          </td>
                          <td className="px-3 py-2">
                            <div className="flex flex-wrap gap-2">
                              <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={busyId === id}
                                onClick={() => openAmountDialog(r)}
                              >
                                {isApproved ? tp("editAmountApproved") : tp("editAmount")}
                              </Button>
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </CardContent>
            </Card>
          )}
        </>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tw("approvedReceiptsTitle")}</CardTitle>
            <CardDescription>{tw("approvedReceiptsHint")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-xs text-muted-foreground">{tp("reviewPendingHint")}</p>
            {approvedReceipts.length === 0 ? (
              <p className="text-sm text-muted-foreground">{tw("approvedReceiptsEmpty")}</p>
            ) : (
              <ul className="space-y-2">
                {approvedReceipts.map((r) => {
                  const id = num(r.id)
                  const amt = num(r.amount)
                  return (
                    <li
                      key={id || String(r.created_at)}
                      className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                    >
                      <span className="font-medium tabular-nums">{formatReceiptAmount(amt, isFa, tp)}</span>
                      <span className="text-muted-foreground">
                        #{formatNumber(id, isFa)} · {String(r.created_at ?? "—")}
                      </span>
                      <Badge variant="secondary">{tw("statusApproved")}</Badge>
                    </li>
                  )
                })}
              </ul>
            )}
          </CardContent>
        </Card>
      )}

      <DataPagination meta={pagination} isFa={isFa} onPageChange={onPageChange} onPerPageChange={onPerPageChange} />

      <Dialog open={Boolean(previewReceipt)} onOpenChange={(o) => !o && setPreviewReceipt(null)}>
        <DialogContent className="max-w-[95vw] sm:max-w-5xl">
          <DialogHeader>
            <DialogTitle>
              {tp("receiptImage")}{" "}
              {previewReceipt ? `#${formatNumber(num(previewReceipt.id), isFa)}` : ""}
            </DialogTitle>
            <DialogDescription>{tp("clickToEnlarge")}</DialogDescription>
          </DialogHeader>
          {previewReceipt && String(previewReceipt.imageUrl ?? "").trim() ? (
            <div className="max-h-[82vh] overflow-auto rounded-md bg-muted/30 p-2">
              <img
                src={String(previewReceipt.imageUrl)}
                alt={`receipt-${String(previewReceipt.id ?? "")}`}
                className="mx-auto max-h-[78vh] w-auto max-w-full rounded-md object-contain"
              />
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(amountTarget)} onOpenChange={(o) => !o && setAmountTarget(null)}>
        <DialogContent className={cn(isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>
              {amountTarget && String(amountTarget.status ?? "") === "approved"
                ? tp("editAmountTitleApproved")
                : tp("editAmountTitle")}
            </DialogTitle>
            <DialogDescription>
              {amountTarget && String(amountTarget.status ?? "") === "approved"
                ? tp("editAmountDescApproved")
                : tp("editAmountDesc")}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label>{tp("colAmount")}</Label>
            <Input dir="ltr" value={amountDraft} onChange={(e) => setAmountDraft(e.target.value)} />
          </div>
          <DialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setAmountTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={Boolean(amountTarget && busyId === num(amountTarget.id))} onClick={() => void saveAmount()}>
              {tp("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={Boolean(rejectTarget)} onOpenChange={(o) => !o && setRejectTarget(null)}>
        <DialogContent className={cn(isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("rejectDialogTitle")}</DialogTitle>
            <DialogDescription>{tp("rejectDialogDesc")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{tp("rejectReason")}</Label>
              <select className={selectClass} value={rejectReason} onChange={(e) => setRejectReason(e.target.value)}>
                {rejectReasons.length === 0 ? <option value="">{tp("noRejectReasons")}</option> : null}
                {rejectReasons.map((reason) => (
                  <option key={reason} value={reason}>
                    {reason}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label>{tp("customRejectReason")}</Label>
              <textarea
                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                value={rejectCustomReason}
                onChange={(e) => setRejectCustomReason(e.target.value)}
                placeholder={tp("customRejectReasonPlaceholder")}
              />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setRejectTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={Boolean(rejectTarget && busyId === num(rejectTarget.id))} onClick={() => void confirmReject()}>
              {tp("reject")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

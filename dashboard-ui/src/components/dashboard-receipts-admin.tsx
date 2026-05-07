"use client"

import { useCallback, useMemo, useState, type ReactNode } from "react"
import { useTranslation } from "react-i18next"

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
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate, type AdminMutateResult } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatDateTime, formatNumber, formatNumericString } from "@/lib/format-locale"
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

function formatReceiptMutateFeedback(res: AdminMutateResult, tp: (k: string) => string): string | null {
  if (!res.ok) {
    return res.message ? `${tp("mutateError")}: ${res.message}` : tp("mutateError")
  }
  const d = res.data
  if (d && typeof d === "object" && "ok" in d && (d as { ok?: unknown }).ok === false) {
    const rec = d as unknown as Record<string, unknown>
    const reason = typeof rec.reason === "string" ? rec.reason : JSON.stringify(d)
    return `${tp("approveFailed")}: ${reason}`
  }
  return null
}

export function DashboardReceiptsAdmin({
  receipts,
  receiptAggregates,
  pagination,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  receipts: DashRecord[]
  receiptAggregates?: unknown
  pagination: PaginationMeta | null
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`receiptsAdmin.${k}`)

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

  const [statusFilter, setStatusFilter] = useState<"all" | "pending" | "approved" | "rejected">("all")
  const [busyId, setBusyId] = useState<number | null>(null)
  const [alertText, setAlertText] = useState<string | null>(null)

  const filteredReceipts = useMemo(() => {
    if (statusFilter === "all") return receipts
    if (statusFilter === "pending") {
      return receipts.filter((r) => {
        const st = String(r.status ?? "")
        return st === "pending" || st === "processing"
      })
    }
    return receipts.filter((r) => String(r.status ?? "") === statusFilter)
  }, [receipts, statusFilter])

  const runReceiptAction = useCallback(
    async (receiptId: number, action: "approve" | "reject") => {
      setBusyId(receiptId)
      setAlertText(null)
      try {
        const res = await postAdminMutate("receipt_action", {
          receipt_id: receiptId,
          svp_receipt_action: action,
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

  const selectClass =
    "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      {alertText ? (
        <div
          role="alert"
          className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100"
        >
          {alertText}
        </div>
      ) : null}

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

      <div className="flex flex-wrap items-center gap-2">
        <LabelInline isFa={isFa}>{tp("filterStatus")}</LabelInline>
        <select
          className={selectClass + " w-auto min-w-[10rem]"}
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)}
        >
          <option value="all">{tp("filterAll")}</option>
          <option value="pending">{tp("statusPending")}</option>
          <option value="approved">{tp("statusApproved")}</option>
          <option value="rejected">{tp("statusRejected")}</option>
        </select>
      </div>

      <p className="text-xs text-muted-foreground">
        {pagination
          ? t("receiptsAdmin.listPaginationHint", { total: formatNumber(pagination.total, isFa) })
          : t("receiptsAdmin.sampleHint", { n: receipts.length })}
      </p>

      <Separator />

      {filteredReceipts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("emptyList")}</p>
      ) : (
        <ul className="space-y-3">
          {filteredReceipts.map((r) => {
            const id = num(r.id)
            const st = String(r.status ?? "")
            const pendingRow = st === "pending"
            const processingRow = st === "processing"
            return (
              <li key={id}>
                <Card>
                  <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2 space-y-0 pb-2">
                    <div className="min-w-0 space-y-1">
                      <CardTitle className="text-base font-mono">#{formatNumber(id, isFa)}</CardTitle>
                      <CardDescription>
                        {tp("user")}: {formatNumericString(String(r.user_id ?? "—"), isFa)} · {tp("amount")}:{" "}
                        {formatNumber(num(r.amount), isFa)} · {tp("created")}:{" "}
                        {formatDateTime(r.created_at as string | undefined, isFa)}
                      </CardDescription>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant={receiptStatusVariant(st)}>{receiptStatusLabel(st, tp)}</Badge>
                      {String(r.imageUrl ?? "").trim() ? (
                        <Dialog>
                          <DialogTrigger asChild>
                            <Button type="button" size="sm" variant="secondary">
                              {tp("viewImage")}
                            </Button>
                          </DialogTrigger>
                          <DialogContent className="sm:max-w-3xl">
                            <DialogHeader>
                              <DialogTitle>
                                {tp("receiptImage")} #{formatNumber(id, isFa)}
                              </DialogTitle>
                            </DialogHeader>
                            <img
                              src={String(r.imageUrl)}
                              alt={`receipt-${id}`}
                              className="max-h-[75vh] w-full rounded-md object-contain"
                              loading="lazy"
                            />
                          </DialogContent>
                        </Dialog>
                      ) : null}
                      {pendingRow ? (
                        <>
                          <Button
                            type="button"
                            size="sm"
                            disabled={busyId === id}
                            onClick={() => void runReceiptAction(id, "approve")}
                          >
                            {tp("approve")}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={busyId === id}
                            onClick={() => void runReceiptAction(id, "reject")}
                          >
                            {tp("reject")}
                          </Button>
                        </>
                      ) : processingRow ? (
                        <>
                          <span className="text-xs text-muted-foreground">{tp("statusProcessing")}</span>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={busyId === id}
                            onClick={() => void runReceiptAction(id, "reject")}
                          >
                            {tp("reject")}
                          </Button>
                        </>
                      ) : null}
                    </div>
                  </CardHeader>
                </Card>
              </li>
            )
          })}
        </ul>
      )}
      <DataPagination
        meta={pagination}
        isFa={isFa}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />
    </div>
  )
}

function LabelInline({ children, isFa }: { children: ReactNode; isFa: boolean }) {
  return <span className={cn("text-sm text-muted-foreground", isFa && "text-right")}>{children}</span>
}

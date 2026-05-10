"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { WholesaleLadderTimeline } from "@/components/dashboard-wholesale-ladder-timeline"
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
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function DashboardResellerFinance({
  wholesaleLines,
  receipts,
  customerCharges,
  actorBalance,
  isFa,
  receiptsPagination,
  onMutateSuccess,
  onReceiptsPageChange,
  onReceiptsPerPageChange,
}: {
  wholesaleLines: DashRecord[]
  receipts: DashRecord[]
  customerCharges: DashRecord[]
  actorBalance: number | undefined
  isFa: boolean
  receiptsPagination: PaginationMeta | null
  onMutateSuccess?: () => void
  onReceiptsPageChange: (page: number) => void
  onReceiptsPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`resellerFinance.${k}`, opts)

  const approvedReceipts = useMemo(() => {
    return receipts.filter((r) => String(r.status ?? "").toLowerCase() === "approved")
  }, [receipts])

  const [topUpAmount, setTopUpAmount] = useState("")
  const [topUpBusy, setTopUpBusy] = useState(false)
  const [topUpMsg, setTopUpMsg] = useState<string | null>(null)

  const onTopUp = async () => {
    const raw = topUpAmount.replace(/,/g, ".").trim()
    const amt = parseFloat(raw)
    if (!Number.isFinite(amt) || amt <= 0) {
      setTopUpMsg(tp("topUpInvalid"))
      return
    }
    setTopUpBusy(true)
    setTopUpMsg(null)
    try {
      const res = await postAdminMutate("reseller_wallet_topup_checkout", { amount: amt })
      if (!res.ok) {
        setTopUpMsg(res.message || res.reason || tp("topUpError"))
        return
      }
      const tid = (res as { transaction_id?: number }).transaction_id
      const botHint = (res as { notify_sent?: boolean }).notify_sent ? tp("topUpSentBot") : tp("topUpNoBot")
      setTopUpMsg(
        tp("topUpQueued", {
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

  return (
    <div className={cn("space-y-8", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
      <div>
        <h2 className="text-xl font-semibold tracking-tight">{tp("title")}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      {typeof actorBalance === "number" ? (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{tp("balanceTitle")}</CardTitle>
            <CardDescription>{tp("balanceHint")}</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
          </CardContent>
        </Card>
      ) : null}

      {wholesaleLines.length > 0 ? (
        <WholesaleLadderTimeline wholesaleLines={wholesaleLines} isFa={isFa} />
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("approvedReceiptsTitle")}</CardTitle>
          <CardDescription>{tp("approvedReceiptsHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {approvedReceipts.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("approvedReceiptsEmpty")}</p>
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
                    <span className="font-medium tabular-nums">{formatNumber(amt, isFa)}</span>
                    <span className="text-muted-foreground">
                      #{formatNumber(id, isFa)} · {String(r.created_at ?? "—")}
                    </span>
                    <Badge variant="secondary">{tp("statusApproved")}</Badge>
                  </li>
                )
              })}
            </ul>
          )}
          <DataPagination
            meta={receiptsPagination}
            isFa={isFa}
            onPageChange={onReceiptsPageChange}
            onPerPageChange={onReceiptsPerPageChange}
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("customerChargesTitle")}</CardTitle>
          <CardDescription>{tp("customerChargesHint")}</CardDescription>
        </CardHeader>
        <CardContent>
          {customerCharges.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("customerChargesEmpty")}</p>
          ) : (
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
                    <span className="text-destructive tabular-nums font-medium">
                      −{formatNumber(amt, isFa)}
                    </span>
                    <span className="min-w-0 flex-1 text-muted-foreground">
                      {tp("customerChargeLine", { name: label || `#${num(row.customer_svp_user_id)}` })}
                    </span>
                    <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                  </li>
                )
              })}
            </ul>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("topUpTitle")}</CardTitle>
          <CardDescription>{tp("topUpHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {topUpMsg ? (
            <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">{topUpMsg}</p>
          ) : null}
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="topup-amt">{tp("topUpAmount")}</Label>
              <Input
                id="topup-amt"
                dir="ltr"
                inputMode="decimal"
                value={topUpAmount}
                onChange={(e) => setTopUpAmount(e.target.value)}
                placeholder={tp("topUpPlaceholder")}
              />
            </div>
            <Button type="button" disabled={topUpBusy} onClick={() => void onTopUp()}>
              {topUpBusy ? "…" : tp("topUpSubmit")}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

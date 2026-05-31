"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
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
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
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

export function DashboardResellerChargeAdmin({
  receipts,
  actorBalance,
  customerCharges = [],
  isFa,
  onMutateSuccess,
}: {
  receipts: DashRecord[]
  actorBalance?: number
  customerCharges?: DashRecord[]
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tc = (k: string, opts?: Record<string, string | number>) => t(`resellerCharge.${k}`, opts)
  const tf = (k: string, opts?: Record<string, string | number>) => t(`resellerFinance.${k}`, opts)

  const approvedReceipts = useMemo(
    () => receipts.filter((r) => String(r.status ?? "").toLowerCase() === "approved"),
    [receipts]
  )

  const [topUpAmount, setTopUpAmount] = useState("")
  const [topUpBusy, setTopUpBusy] = useState(false)
  const [topUpMsg, setTopUpMsg] = useState<string | null>(null)

  async function onTopUp() {
    const raw = topUpAmount.replace(/,/g, ".").trim()
    const amt = parseFloat(raw)
    if (!Number.isFinite(amt) || amt <= 0) {
      setTopUpMsg(tf("topUpInvalid"))
      return
    }
    setTopUpBusy(true)
    setTopUpMsg(null)
    try {
      const res = await postAdminMutate("reseller_wallet_topup_checkout", { amount: amt })
      if (!res.ok) {
        setTopUpMsg(adminMutateErrorText(res, tf("topUpError")))
        return
      }
      const tid = (res as { transaction_id?: number }).transaction_id
      const botHint = (res as { notify_sent?: boolean }).notify_sent ? tf("topUpSentBot") : tf("topUpNoBot")
      setTopUpMsg(
        tf("topUpQueued", {
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
    <div className={dashPageRootClass(isFa)} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tc("title")} description={tc("subtitle")} />

      {typeof actorBalance === "number" ? (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{tf("balanceTitle")}</CardTitle>
            <CardDescription>{tc("balanceHint")}</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("topUpTitle")}</CardTitle>
          <CardDescription>{tf("topUpHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {topUpMsg ? (
            <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">{topUpMsg}</p>
          ) : null}
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="reseller-topup-amt">{tf("topUpAmount")}</Label>
              <Input
                id="reseller-topup-amt"
                dir="ltr"
                inputMode="decimal"
                value={topUpAmount}
                onChange={(e) => setTopUpAmount(e.target.value)}
                placeholder={tf("topUpPlaceholder")}
              />
            </div>
            <Button type="button" disabled={topUpBusy} onClick={() => void onTopUp()}>
              {topUpBusy ? "…" : tf("topUpSubmit")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("approvedReceiptsTitle")}</CardTitle>
          <CardDescription>{tf("approvedReceiptsHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {approvedReceipts.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tf("approvedReceiptsEmpty")}</p>
          ) : (
            <ul className="space-y-2">
              {approvedReceipts.map((r) => {
                const id = num(r.id)
                const amt = num(r.amount)
                const label = receiptUserLabel(r)
                return (
                  <li
                    key={id || String(r.created_at)}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                  >
                    <span className="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">
                      +{formatNumber(amt, isFa)} {tc("tomanUnit")}
                    </span>
                    <span className="min-w-0 flex-1 text-muted-foreground">{label}</span>
                    <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                    <Badge variant="secondary">{tf("statusApproved")}</Badge>
                  </li>
                )
              })}
            </ul>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("customerChargesTitle")}</CardTitle>
          <CardDescription>{tc("customerChargesHint")}</CardDescription>
        </CardHeader>
        <CardContent>
          {customerCharges.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tc("customerChargesEmpty")}</p>
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
                    <span className="font-medium tabular-nums text-destructive">
                      {tc("customerChargeLine", {
                        amount: formatNumber(amt, isFa),
                        name: label || `#${num(row.customer_svp_user_id)}`,
                      })}
                    </span>
                    <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                  </li>
                )
              })}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

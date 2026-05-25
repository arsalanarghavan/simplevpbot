"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function asRecord(v: unknown): DashRecord {
  return v && typeof v === "object" ? (v as DashRecord) : {}
}

export function DashboardReferralAdmin({
  settings,
  referralStats,
  referralEvents,
  eventsPagination,
  readOnlySettings = false,
  isFa,
  onMutateSuccess,
  onEventsPageChange,
  onEventsPerPageChange,
}: {
  settings: DashRecord | undefined
  referralStats: unknown
  referralEvents: DashRecord[]
  eventsPagination: PaginationMeta | null
  /** Hide global referral program settings (resellers see scoped stats only). */
  readOnlySettings?: boolean
  isFa: boolean
  onMutateSuccess?: () => void
  onEventsPageChange?: (page: number) => void
  onEventsPerPageChange?: (n: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`referralAdmin.${k}`)
  const s = settings ?? {}
  const stats =
    referralStats != null && typeof referralStats === "object" ? asRecord(referralStats) : null
  const summary = stats ? asRecord(stats.summary) : {}

  const initial = useMemo(
    () => ({
      referral_enabled: bool(s.referral_enabled),
      referral_percent: String(s.referral_percent ?? "0"),
      referral_min_payout_base: String(s.referral_min_payout_base ?? "0"),
      referral_example_base_toman: String(s.referral_example_base_toman ?? "170000"),
      referral_example_invite_count: String(Math.max(1, num(s.referral_example_invite_count) || 10)),
      referral_require_approved_referrer: bool(s.referral_require_approved_referrer),
      telegram_bot_username: String(s.telegram_bot_username ?? ""),
      bale_bot_username: String(s.bale_bot_username ?? ""),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "referral",
        referral_enabled: form.referral_enabled ? 1 : 0,
        referral_percent: form.referral_percent.trim(),
        referral_min_payout_base: form.referral_min_payout_base.trim(),
        referral_example_base_toman: form.referral_example_base_toman.trim(),
        referral_example_invite_count: num(form.referral_example_invite_count),
        referral_require_approved_referrer: form.referral_require_approved_referrer ? 1 : 0,
        telegram_bot_username: form.telegram_bot_username.trim(),
        bale_bot_username: form.bale_bot_username.trim(),
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const top =
    stats && Array.isArray(stats.topReferrers) ? (stats.topReferrers as DashRecord[]) : []

  const hasEvents = referralEvents.length > 0 || (eventsPagination && eventsPagination.total > 0)
  const showDataGrid = Boolean(stats) || hasEvents

  return (
    <div className={cn("mx-auto w-full max-w-7xl space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      {showDataGrid ? (
        <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
          <div className="min-w-0 space-y-4">
            {stats ? (
              <div className="grid gap-4 sm:grid-cols-2">
                <StatCard label={tp("statEvents30")} value={formatNumber(num(summary.eventsLast30), isFa)} />
                <StatCard label={tp("statInvitedUsers")} value={formatNumber(num(summary.invitedUsersWithReferrer), isFa)} />
                <StatCard label={tp("statCommissionPaid")} value={formatNumber(num(summary.totalCommissionPaid), isFa)} />
                <StatCard
                  label={tp("statReferralOnPurchases")}
                  value={formatNumber(num(summary.totalReferralAmountOnPurchases), isFa)}
                />
              </div>
            ) : null}

            {stats && top.length > 0 ? (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{tp("topReferrers")}</CardTitle>
                  <CardDescription>{tp("topReferrersDesc")}</CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                  <table
                    className={cn(
                      "w-full min-w-[28rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
                      isFa ? "text-right" : "text-left"
                    )}
                  >
                    <thead>
                      <tr>
                        <th className="p-2">#</th>
                        <th className="p-2">{tp("colReferrer")}</th>
                        <th className="p-2">{tp("colDirectInvites")}</th>
                        <th className="p-2">{tp("colCommissionCount")}</th>
                        <th className="p-2">{tp("colCommissionSum")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {top.map((row, i) => {
                        const id = num(row.referrerId)
                        const label =
                          String(row.username || "").trim() !== ""
                            ? `@${String(row.username)}`
                            : `${String(row.firstName || "").trim() || "—"} (#${formatNumber(id, isFa)})`
                        return (
                          <tr key={id || i}>
                            <td className="p-2 text-muted-foreground">{i + 1}</td>
                            <td className="p-2 font-mono text-xs">{label}</td>
                            <td className="p-2">{formatNumber(num(row.directInvites), isFa)}</td>
                            <td className="p-2">{formatNumber(num(row.commissionCount), isFa)}</td>
                            <td className="p-2">{formatNumber(num(row.commissionTotal), isFa)}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </CardContent>
              </Card>
            ) : null}
          </div>

          {hasEvents ? (
            <Card className="min-w-0">
              <CardHeader>
                <CardTitle className="text-base">{tp("recentEvents")}</CardTitle>
                <CardDescription>{tp("recentEventsDesc")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="overflow-x-auto">
                  <table
                    className={cn(
                      "w-full min-w-[36rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
                      isFa ? "text-right" : "text-left"
                    )}
                  >
                    <thead>
                      <tr>
                        <th className="p-2">{tp("colTime")}</th>
                        <th className="p-2">{tp("colInviter")}</th>
                        <th className="p-2">{tp("colPlatform")}</th>
                        <th className="p-2">{tp("colOutcome")}</th>
                        <th className="p-2">{tp("colVisitor")}</th>
                        <th className="p-2">{tp("colPayload")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {referralEvents.map((ev) => (
                        <tr key={String(ev.id ?? "")}>
                          <td className="p-2 whitespace-nowrap">
                            {ev.created_at ? formatDateTime(String(ev.created_at), isFa) : "—"}
                          </td>
                          <td className="p-2 font-mono">{formatNumber(num(ev.inviter_svp_user_id), isFa)}</td>
                          <td className="p-2">{String(ev.platform ?? "")}</td>
                          <td className="p-2">{String(ev.outcome ?? "")}</td>
                          <td className="p-2 font-mono">{formatNumber(num(ev.resulting_svp_user_id), isFa)}</td>
                          <td className="max-w-[10rem] truncate p-2 font-mono">{String(ev.start_payload ?? "")}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <DataPagination
                  meta={eventsPagination}
                  isFa={isFa}
                  onPageChange={(p) => onEventsPageChange?.(p)}
                  onPerPageChange={(n) => onEventsPerPageChange?.(n)}
                />
              </CardContent>
            </Card>
          ) : (
            <div className="hidden lg:block" aria-hidden />
          )}
        </div>
      ) : null}

      {readOnlySettings ? null : (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("cardTitle")}</CardTitle>
            <CardDescription>{tp("cardDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.referral_enabled}
                onChange={(e) => setForm((f) => ({ ...f, referral_enabled: e.target.checked }))}
              />
              {tp("enabled")}
            </label>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="r_pct">{tp("percent")}</Label>
                <Input
                  id="r_pct"
                  inputMode="decimal"
                  value={form.referral_percent}
                  onChange={(e) => setForm((f) => ({ ...f, referral_percent: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_min">{tp("minPayout")}</Label>
                <Input
                  id="r_min"
                  inputMode="decimal"
                  value={form.referral_min_payout_base}
                  onChange={(e) => setForm((f) => ({ ...f, referral_min_payout_base: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_ex_base">{tp("exampleBase")}</Label>
                <Input
                  id="r_ex_base"
                  inputMode="decimal"
                  value={form.referral_example_base_toman}
                  onChange={(e) => setForm((f) => ({ ...f, referral_example_base_toman: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="r_ex_n">{tp("exampleInvites")}</Label>
                <Input
                  id="r_ex_n"
                  type="number"
                  min={1}
                  value={form.referral_example_invite_count}
                  onChange={(e) => setForm((f) => ({ ...f, referral_example_invite_count: e.target.value }))}
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="r_tg">{tp("telegramBotUsername")}</Label>
                <Input
                  id="r_tg"
                  value={form.telegram_bot_username}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_bot_username: e.target.value }))}
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="r_bl">{tp("baleBotUsername")}</Label>
                <Input
                  id="r_bl"
                  value={form.bale_bot_username}
                  onChange={(e) => setForm((f) => ({ ...f, bale_bot_username: e.target.value }))}
                />
              </div>
            </div>
            <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.referral_require_approved_referrer}
                onChange={(e) => setForm((f) => ({ ...f, referral_require_approved_referrer: e.target.checked }))}
              />
              {tp("requireApproved")}
            </label>
            {error ? (
              <div
                role="alert"
                className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
              >
                {error}
              </div>
            ) : null}
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {tp("save")}
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle className="text-xl tabular-nums">{value}</CardTitle>
      </CardHeader>
    </Card>
  )
}

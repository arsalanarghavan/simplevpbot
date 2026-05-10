"use client"

import { CheckIcon } from "lucide-react"
import { useTranslation } from "react-i18next"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export type LadderSnapshot = {
  total_gb?: unknown
  total_wholesale_toman?: unknown
  current_tier_id?: unknown
  next_tier_id?: unknown
  next_price_per_gb?: unknown
  gb_to_next_tier?: unknown
  toman_to_next_tier?: unknown
  tiers?: Array<{
    id?: unknown
    sort_order?: unknown
    price_per_gb?: unknown
    min_total_gb?: unknown
    min_total_toman?: unknown
  }>
}

function tierThresholdLabel(t: {
  min_total_gb?: unknown
  min_total_toman?: unknown
}, isFa: boolean): string {
  const mg = num(t.min_total_gb)
  const mt = num(t.min_total_toman)
  const parts: string[] = []
  if (mg > 0) parts.push(`${formatNumber(mg, isFa)} GB`)
  if (mt > 0) parts.push(`${formatNumber(mt, isFa)}`)
  return parts.join(" · ")
}

export function WholesaleLadderTimeline({
  wholesaleLines,
  isFa,
  className,
}: {
  wholesaleLines: DashRecord[]
  isFa: boolean
  className?: string
}) {
  const { t } = useTranslation()

  if (!wholesaleLines.length) return null

  return (
    <div className={cn("space-y-4", className)} dir={isFa ? "rtl" : "ltr"}>
      <div>
        <h3 className="text-base font-semibold">{t("plansAdmin.ladderTitle")}</h3>
        <p className="text-sm text-muted-foreground">{t("plansAdmin.ladderTimelineSubtitle")}</p>
      </div>
      <div className="grid gap-4 md:grid-cols-1 xl:grid-cols-2">
        {wholesaleLines.map((line) => {
          const lid = num(line.id)
          const ladder = line.ladder as LadderSnapshot | undefined
          const tiersRaw = ladder?.tiers ?? []
          const tiers = [...tiersRaw].sort(
            (a, b) => num(a.sort_order) - num(b.sort_order)
          )
          const curId = ladder?.current_tier_id != null ? num(ladder.current_tier_id) : 0
          const curIdxRaw = curId > 0 ? tiers.findIndex((x) => num(x.id) === curId) : 0
          const curIdx = curIdxRaw >= 0 ? curIdxRaw : 0

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
                <CardDescription className="text-xs">
                  {t("plansAdmin.ladderTotalGb")}: {formatNumber(num(ladder?.total_gb), isFa)} ·{" "}
                  {t("plansAdmin.ladderTotalToman")}:{" "}
                  {formatNumber(num(ladder?.total_wholesale_toman), isFa)}
                </CardDescription>
              </CardHeader>
              <CardContent>
                {tiers.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t("plansAdmin.ladderNoTiers")}</p>
                ) : (
                  <div className="overflow-x-auto pb-2">
                    <div className="flex min-w-0 items-start gap-0">
                      {tiers.map((tier, idx) => {
                        const tid = num(tier.id)
                        const price = num(tier.price_per_gb)
                        const done = curIdx >= 0 && idx < curIdx
                        const current = curIdx >= 0 && idx === curIdx
                        const future = curIdx >= 0 && idx > curIdx
                        const thresh = tierThresholdLabel(tier, isFa)

                        return (
                          <div key={tid || idx} className="flex min-w-[5.5rem] flex-1 flex-col items-center">
                            <div className="flex w-full items-center">
                              {idx > 0 ? (
                                <div
                                  className={cn(
                                    "h-0.5 flex-1 shrink-0",
                                    idx <= curIdx ? "bg-emerald-500/70" : "bg-muted"
                                  )}
                                  aria-hidden
                                />
                              ) : (
                                <span className="flex-1" />
                              )}
                              <div
                                className={cn(
                                  "relative flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold tabular-nums",
                                  done &&
                                    "border-emerald-600 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
                                  current &&
                                    "border-primary bg-primary/15 text-primary ring-2 ring-primary/30",
                                  future && "border-muted-foreground/40 bg-muted/40 text-muted-foreground"
                                )}
                              >
                                {done ? (
                                  <CheckIcon className="size-4 text-emerald-600 dark:text-emerald-400" />
                                ) : (
                                  String(idx + 1)
                                )}
                              </div>
                              {idx < tiers.length - 1 ? (
                                <div
                                  className={cn(
                                    "h-0.5 flex-1 shrink-0",
                                    idx < curIdx ? "bg-emerald-500/70" : "bg-muted"
                                  )}
                                  aria-hidden
                                />
                              ) : (
                                <span className="flex-1" />
                              )}
                            </div>
                            <p className="mt-2 max-w-[7rem] text-center text-[11px] font-medium tabular-nums leading-snug">
                              {formatNumber(price, isFa)}
                            </p>
                            {thresh ? (
                              <p className="max-w-[7rem] text-center text-[10px] text-muted-foreground leading-tight">
                                {thresh}
                              </p>
                            ) : null}
                            {future ? (
                              <p className="mt-0.5 max-w-[7rem] text-center text-[10px] text-muted-foreground">
                                {t("plansAdmin.ladderTierCheaperHint")}
                              </p>
                            ) : null}
                          </div>
                        )
                      })}
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          )
        })}
      </div>
    </div>
  )
}

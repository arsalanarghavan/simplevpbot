"use client"

import { useMemo } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelAllowed(row: Record<string, unknown> | undefined): boolean {
  if (!row) return false
  const acc = row.panel_access === true || row.panel_access === 1 || row.panel_access === "1"
  const price = Number(String(row.price_per_gb ?? "").replace(/,/g, ""))
  return acc || (Number.isFinite(price) && price > 0)
}

export function DashboardResellerPanelsAdmin({
  panels,
  resellerPanelPricesMap,
  isFa,
}: {
  panels: DashRecord[]
  resellerPanelPricesMap: Record<string, Array<Record<string, unknown>> | undefined>
  isFa: boolean
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`resellerPanelsAdmin.${k}`, opts)

  const rows = useMemo(() => {
    const accessCount = new Map<number, number>()
    for (const list of Object.values(resellerPanelPricesMap)) {
      if (!Array.isArray(list)) continue
      for (const row of list) {
        if (!panelAllowed(row)) continue
        const pid = num(row.panel_id)
        if (pid < 1) continue
        accessCount.set(pid, (accessCount.get(pid) ?? 0) + 1)
      }
    }
    return panels
      .map((p) => {
        const id = num(p.id)
        return {
          id,
          label: String(p.label ?? p.name ?? `#${id}`),
          active: p.active === true || p.active === 1 || p.active === "1",
          resellerCount: accessCount.get(id) ?? 0,
        }
      })
      .sort((a, b) => a.label.localeCompare(b.label))
  }, [panels, resellerPanelPricesMap])

  return (
    <div className={dashPageRootClass(isFa, "mx-auto w-full max-w-5xl")} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("tableTitle")}</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table
            className={cn(
              "w-full min-w-[28rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              "text-start"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">{tp("colPanel")}</th>
                <th className="p-2 font-medium">{tp("colStatus")}</th>
                <th className="p-2 font-medium">{tp("colResellers")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={3} className="p-4 text-center text-muted-foreground">
                    {tp("empty")}
                  </td>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id}>
                    <td className="p-2 font-medium">{row.label}</td>
                    <td className="p-2">
                      <Badge variant={row.active ? "default" : "secondary"}>
                        {row.active ? tp("statusActive") : tp("statusInactive")}
                      </Badge>
                    </td>
                    <td className="p-2 tabular-nums">{formatNumber(row.resellerCount, isFa)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <p className="text-xs text-muted-foreground">{tp("hint")}</p>
    </div>
  )
}

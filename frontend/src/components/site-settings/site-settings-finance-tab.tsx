"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function SiteSettingsFinanceTab({
  settings,
  dashboardBaseUrl,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  dashboardBaseUrl: string
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { ltrCell } = useDashLocale()
  const tf = (k: string) => t(`siteSettings.finance.${k}`)

  const initial = useMemo(
    () => ({
      notify_panel_cost_expiry: bool(settings?.notify_panel_cost_expiry ?? true),
      panel_cost_reminder_days: String(settings?.panel_cost_reminder_days ?? "7,1,0"),
      panel_cost_extend_days_on_paid: String(settings?.panel_cost_extend_days_on_paid ?? 30),
    }),
    [settings]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])
  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const onSave = useCallback(async () => {
    await saveSettingsTab("finance", {
        notify_panel_cost_expiry: form.notify_panel_cost_expiry ? 1 : 0,
        panel_cost_reminder_days: form.panel_cost_reminder_days.trim(),
        panel_cost_extend_days_on_paid: Math.max(1, Number(form.panel_cost_extend_days_on_paid) || 30),
      })
  }, [form, saveSettingsTab])

  const economicsUrl = `${dashboardBaseUrl.replace(/\/$/, "")}/unit_economics/`

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("title")}</CardTitle>
          <CardDescription>{tf("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={form.notify_panel_cost_expiry}
              onChange={(e) =>
                setForm((f) => ({ ...f, notify_panel_cost_expiry: e.target.checked }))
              }
            />
            {tf("notifyEnabled")}
          </label>
          <div className="space-y-2">
            <Label htmlFor="pc_days">{tf("reminderDays")}</Label>
            <Input
              id="pc_days"
              value={form.panel_cost_reminder_days}
              onChange={(e) =>
                setForm((f) => ({ ...f, panel_cost_reminder_days: e.target.value }))
              }
              placeholder="7,1,0"
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tf("reminderDaysHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="pc_extend">{tf("extendDaysOnPaid")}</Label>
            <Input
              id="pc_extend"
              type="number"
              min={1}
              max={365}
              value={form.panel_cost_extend_days_on_paid}
              onChange={(e) =>
                setForm((f) => ({ ...f, panel_cost_extend_days_on_paid: e.target.value }))
              }
            />
          </div>
          <p className="text-sm">
            <a href={economicsUrl} className="text-primary underline-offset-2 hover:underline">
              {tf("openUnitEconomics")}
            </a>
          </p>
        </CardContent>
      </Card>
      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      <Button type="button" disabled={saving} onClick={() => void onSave()}>
        {tf("save")}
      </Button>
    </div>
  )
}

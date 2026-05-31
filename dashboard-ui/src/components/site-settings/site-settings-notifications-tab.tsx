"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function daysToString(raw: unknown): string {
  if (Array.isArray(raw)) {
    return (raw as unknown[]).map((x) => String(Number(x))).filter((x) => x !== "NaN").join(",")
  }
  return String(raw ?? "3,1")
}

export function SiteSettingsNotificationsTab({
  settings,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`siteSettings.notifications.${k}`)
  const tn = (k: string) => t(`notificationsAdmin.${k}`)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      notify_low_traffic_percent: String(num(s.notify_low_traffic_percent) || 10),
      notify_expiry_days: daysToString(s.notify_expiry_days),
      notify_user_volume: bool(s.notify_user_volume ?? true),
      notify_user_expiry: bool(s.notify_user_expiry ?? true),
      notify_user_users: bool(s.notify_user_users ?? true),
      notify_user_after_expire: bool(s.notify_user_after_expire ?? true),
      notify_idle_enabled: bool(s.notify_idle_enabled),
      notify_idle_after_days: String(Math.max(7, num(s.notify_idle_after_days) || 45)),
      notify_idle_cooldown_days: String(Math.max(7, num(s.notify_idle_cooldown_days) || 90)),
      notify_admin_panel_down: bool(s.notify_admin_panel_down ?? true),
      notify_admin_panel_down_cooldown: String(Math.max(5, num(s.notify_admin_panel_down_cooldown) || 30)),
      alert_ip_warn_min_distinct: String(Math.max(1, num(s.alert_ip_warn_min_distinct) || 3)),
      alert_ip_warn_hysteresis: bool(s.alert_ip_warn_hysteresis ?? true),
      alert_ip_warn_cooldown_minutes: String(Math.max(0, num(s.alert_ip_warn_cooldown_minutes) || 0)),
    }),
    [s])

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className={cn("flex items-center gap-2 text-sm")}>
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {tn(labelKey)}
    </label>
  )

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "notifications",
        notify_low_traffic_percent: num(form.notify_low_traffic_percent),
        notify_expiry_days: form.notify_expiry_days.trim(),
        notify_user_volume: form.notify_user_volume ? 1 : 0,
        notify_user_expiry: form.notify_user_expiry ? 1 : 0,
        notify_user_users: form.notify_user_users ? 1 : 0,
        notify_user_after_expire: form.notify_user_after_expire ? 1 : 0,
        notify_idle_enabled: form.notify_idle_enabled ? 1 : 0,
        notify_idle_after_days: Math.max(7, num(form.notify_idle_after_days)),
        notify_idle_cooldown_days: Math.max(7, num(form.notify_idle_cooldown_days)),
        notify_admin_panel_down: form.notify_admin_panel_down ? 1 : 0,
        notify_admin_panel_down_cooldown: Math.max(5, num(form.notify_admin_panel_down_cooldown)),
        alert_ip_warn_min_distinct: Math.max(1, num(form.alert_ip_warn_min_distinct)),
        alert_ip_warn_hysteresis: form.alert_ip_warn_hysteresis ? 1 : 0,
        alert_ip_warn_cooldown_minutes: Math.max(0, num(form.alert_ip_warn_cooldown_minutes)),
      })
      if (!res.ok) {
        setError(res.message || tn("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tn])

  return (
    <div className={cn("mx-auto max-w-2xl space-y-6", isFa && "text-right")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("cardTitle")}</CardTitle>
          <CardDescription>{tn("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="n_low">{tn("lowTrafficPercent")}</Label>
            <Input
              id="n_low"
              type="number"
              min={1}
              value={form.notify_low_traffic_percent}
              onChange={(e) => setForm((f) => ({ ...f, notify_low_traffic_percent: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="n_days">{tn("expiryDays")}</Label>
            <Input
              id="n_days"
              value={form.notify_expiry_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_expiry_days: e.target.value }))}
              placeholder={tn("expiryDaysPlaceholder")}
            />
            <p className="text-xs text-muted-foreground">{tn("expiryDaysHint")}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("rulesTitle")}</CardTitle>
          <CardDescription>{tn("rulesDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {chk("notify_user_volume", "ruleVolume")}
          {chk("notify_user_expiry", "ruleExpiry")}
          {chk("notify_user_users", "ruleUsers")}
          {chk("notify_user_after_expire", "ruleAfterExpire")}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("idleTitle")}</CardTitle>
          <CardDescription>{tn("idleDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {chk("notify_idle_enabled", "idleEnabled")}
          <div className="space-y-2">
            <Label htmlFor="idle_after">{tn("idleAfterDays")}</Label>
            <Input
              id="idle_after"
              type="number"
              min={7}
              value={form.notify_idle_after_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_idle_after_days: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="idle_cool">{tn("idleCooldownDays")}</Label>
            <Input
              id="idle_cool"
              type="number"
              min={7}
              value={form.notify_idle_cooldown_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_idle_cooldown_days: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("adminTitle")}</CardTitle>
          <CardDescription>{tn("adminDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {chk("notify_admin_panel_down", "adminPanelDown")}
          <div className="space-y-2">
            <Label htmlFor="adm_cool">{tn("adminCooldown")}</Label>
            <Input
              id="adm_cool"
              type="number"
              min={5}
              value={form.notify_admin_panel_down_cooldown}
              onChange={(e) => setForm((f) => ({ ...f, notify_admin_panel_down_cooldown: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("ipTitle")}</CardTitle>
          <CardDescription>{tp("ipDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="ip_min">{tp("ipMinDistinct")}</Label>
            <Input
              id="ip_min"
              type="number"
              min={1}
              value={form.alert_ip_warn_min_distinct}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_min_distinct: e.target.value }))}
            />
          </div>
          <label className={cn("flex items-center gap-2 text-sm")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={form.alert_ip_warn_hysteresis}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_hysteresis: e.target.checked }))}
            />
            {tp("ipHysteresis")}
          </label>
          <div className="space-y-2">
            <Label htmlFor="ip_cool">{tp("ipCooldownMinutes")}</Label>
            <Input
              id="ip_cool"
              type="number"
              min={0}
              value={form.alert_ip_warn_cooldown_minutes}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_cooldown_minutes: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}
      <Button type="button" disabled={saving} onClick={() => void onSave()}>
        {tn("save")}
      </Button>
    </div>
  )
}

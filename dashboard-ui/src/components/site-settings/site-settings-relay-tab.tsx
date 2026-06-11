"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function formatSyncAt(ts: unknown): string {
  const n = Number(ts)
  if (!Number.isFinite(n) || n < 1) return "—"
  try {
    return new Date(n * 1000).toLocaleString()
  } catch {
    return String(n)
  }
}

function asStringList(v: unknown): string[] {
  if (!Array.isArray(v)) return []
  return v.map((x) => String(x)).filter(Boolean)
}

export function SiteSettingsRelayTab({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { ltrCell } = useDashLocale()
  const tr = (k: string) => t(`siteSettings.relay.${k}`)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      telegram_relay_enabled: bool(s.telegram_relay_enabled),
      telegram_relay_force: bool(s.telegram_relay_force),
      telegram_relay_base_url: String(s.telegram_relay_base_url ?? ""),
      telegram_relay_public_url: String(s.telegram_relay_public_url ?? ""),
      telegram_relay_wp_forward_url: String(s.telegram_relay_wp_forward_url ?? ""),
      telegram_relay_allowed_ips: String(s.telegram_relay_allowed_ips ?? ""),
      telegram_relay_shared_secret: "",
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  const [revealedSecret, setRevealedSecret] = useState<string | null>(null)
  const [relayStatus, setRelayStatus] = useState<DashRecord | null>(null)
  useEffect(() => setForm(initial), [initial])
  const { saving, error, okMsg, saveSettingsTab, setError } = useSiteSettingsSave(onMutateSuccess)
  const [busy, setBusy] = useState("")
  const [actionMsg, setActionMsg] = useState<string | null>(null)
  const secretSet = bool(s.telegram_relay_shared_secret_set)
  const lastSync = formatSyncAt(s.telegram_relay_last_sync_at)
  const tenantId = String(s.telegram_relay_tenant_id ?? relayStatus?.tenant_id ?? "")
  const localDomains = asStringList(s.telegram_relay_domains)
  const remoteDomains = asStringList(relayStatus?.domains)
  const domains = remoteDomains.length > 0 ? remoteDomains : localDomains

  const refreshStatus = useCallback(async () => {
    if (!bool(s.telegram_relay_enabled) && !bool(s.telegram_relay_force)) return
    const res = await postAdminMutate("telegram_relay_status", {})
    if (res.ok && res.data && typeof res.data === "object") {
      setRelayStatus(res.data as DashRecord)
    }
  }, [s.telegram_relay_enabled, s.telegram_relay_force])

  useEffect(() => {
    void refreshStatus()
  }, [refreshStatus])

  const onSave = useCallback(async () => {
    const payload: Record<string, unknown> = {
      telegram_relay_enabled: form.telegram_relay_enabled ? 1 : 0,
      telegram_relay_force: form.telegram_relay_force ? 1 : 0,
      telegram_relay_base_url: form.telegram_relay_base_url,
      telegram_relay_public_url: form.telegram_relay_public_url,
      telegram_relay_wp_forward_url: form.telegram_relay_wp_forward_url,
      telegram_relay_allowed_ips: form.telegram_relay_allowed_ips,
    }
    if (form.telegram_relay_shared_secret.trim() !== "") {
      payload.telegram_relay_shared_secret = form.telegram_relay_shared_secret
    }
    await saveSettingsTab("relay", payload)
  }, [form, saveSettingsTab])

  const runAction = useCallback(
    async (op: string, labelKey: string) => {
      setBusy(op)
      setActionMsg(null)
      setError(null)
      try {
        const res = await postAdminMutate(op, {})
        if (res.ok) {
          const data = res.data as Record<string, unknown> | undefined
          if (op === "telegram_relay_rotate_secret" && typeof data?.secret === "string") {
            setRevealedSecret(data.secret)
            setActionMsg(tr("rotateOk"))
          } else if (op === "telegram_relay_test" || op === "telegram_relay_status") {
            if (data) setRelayStatus(data)
            const uptime = data?.uptime_sec != null ? String(data.uptime_sec) : ""
            setActionMsg(uptime ? tr("testOkUptime").replace("{{sec}}", uptime) : tr("testOk"))
          } else if (op === "telegram_relay_sync") {
            setActionMsg(tr("syncOk"))
          } else if (op === "telegram_relay_domains_sync") {
            setActionMsg(tr("domainsSyncOk"))
            if (data) setRelayStatus((prev) => ({ ...prev, domains: data.domains }))
          } else if (op === "telegram_relay_set_webhook") {
            setActionMsg(tr("webhookOk"))
          } else {
            setActionMsg(tr(labelKey))
          }
          onMutateSuccess?.()
          if (op !== "telegram_relay_status") await refreshStatus()
        } else {
          setActionMsg(res.message || tr("actionFail"))
        }
      } finally {
        setBusy("")
      }
    },
    [onMutateSuccess, refreshStatus, setError, tr]
  )

  const row = cn("flex items-center justify-between gap-3")
  const queueDepth = relayStatus?.forward_queue_depth

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tr("statusTitle")}</CardTitle>
          <CardDescription>{tr("statusDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-muted-foreground">
          <p>
            {tr("tenantId")}: <span className="font-mono text-foreground" dir="ltr">{tenantId || "—"}</span>
          </p>
          <p>
            {tr("lastSync")}: <span dir="ltr">{lastSync}</span>
          </p>
          {queueDepth != null ? (
            <p>
              {tr("queueDepth")}: <span dir="ltr">{String(queueDepth)}</span>
            </p>
          ) : null}
          {domains.length > 0 ? (
            <div>
              <p className="mb-1">{tr("registeredDomains")}</p>
              <ul className="list-inside list-disc font-mono text-xs text-foreground" dir="ltr">
                {domains.map((d) => (
                  <li key={d}>{d}</li>
                ))}
              </ul>
            </div>
          ) : (
            <p>{tr("noDomains")}</p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tr("title")}</CardTitle>
          <CardDescription>{tr("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className={row}>
            <Label>{tr("enabled")}</Label>
            <Switch
              checked={form.telegram_relay_enabled}
              onCheckedChange={(v) => setForm((f) => ({ ...f, telegram_relay_enabled: v }))}
            />
          </div>
          <div className={row}>
            <Label>{tr("force")}</Label>
            <Switch
              checked={form.telegram_relay_force}
              onCheckedChange={(v) => setForm((f) => ({ ...f, telegram_relay_force: v }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="relay_base">{tr("baseUrl")}</Label>
            <Input
              id="relay_base"
              value={form.telegram_relay_base_url}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_base_url: e.target.value }))}
              placeholder="https://tg-relay.example.com"
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tr("baseUrlHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="relay_public">{tr("publicUrl")}</Label>
            <Input
              id="relay_public"
              value={form.telegram_relay_public_url}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_public_url: e.target.value }))}
              placeholder={tr("publicUrlPlaceholder")}
              dir="ltr"
              className={ltrCell("font-mono")}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="relay_wp">{tr("wpForwardUrl")}</Label>
            <Input
              id="relay_wp"
              value={form.telegram_relay_wp_forward_url}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_wp_forward_url: e.target.value }))}
              placeholder={tr("wpForwardPlaceholder")}
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tr("wpForwardHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="relay_ips">{tr("allowedIps")}</Label>
            <Input
              id="relay_ips"
              value={form.telegram_relay_allowed_ips}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_allowed_ips: e.target.value }))}
              placeholder="203.0.113.10,203.0.113.11"
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tr("allowedIpsHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="relay_secret">{tr("sharedSecret")}</Label>
            <Input
              id="relay_secret"
              type="password"
              value={form.telegram_relay_shared_secret}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_shared_secret: e.target.value }))}
              placeholder={secretSet ? "••••••••" : ""}
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tr("sharedSecretHint")}</p>
            {revealedSecret ? (
              <p className="rounded-md border bg-muted/40 p-2 font-mono text-xs" dir="ltr">
                {revealedSecret}
              </p>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      {actionMsg ? <p className="text-sm text-muted-foreground">{actionMsg}</p> : null}
      <div className={cn("flex flex-wrap gap-2")}>
        <Button type="button" disabled={saving} onClick={() => void onSave()}>
          {tr("save")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_status", "testOk")}
        >
          {tr("refreshStatus")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_test", "testOk")}
        >
          {tr("testConnection")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_sync", "syncOk")}
        >
          {tr("syncConfig")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_domains_sync", "domainsSyncOk")}
        >
          {tr("syncDomains")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_set_webhook", "webhookOk")}
        >
          {tr("setWebhook")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={busy !== ""}
          onClick={() => void runAction("telegram_relay_rotate_secret", "rotateOk")}
        >
          {tr("rotateSecret")}
        </Button>
      </div>
    </div>
  )
}

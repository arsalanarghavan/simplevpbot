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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function DashboardBackupAdmin({
  settings,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`backupAdmin.${k}`, opts)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      backup_interval_minutes: String(Math.max(5, num(s.backup_interval_minutes) || 60)),
      backup_telegram_chat_id: String(num(s.backup_telegram_chat_id)),
      backup_bale_chat_id: String(num(s.backup_bale_chat_id)),
      backup_send_telegram_admins: bool(s.backup_send_telegram_admins),
      backup_send_bale_admins: bool(s.backup_send_bale_admins),
      backup_send_telegram_channel: bool(s.backup_send_telegram_channel),
      backup_send_bale_channel: bool(s.backup_send_bale_channel),
      backup_store_on_site: bool(s.backup_store_on_site),
      backup_site_retention_count: String(Math.max(1, Math.min(500, num(s.backup_site_retention_count) || 14))),
      backup_max_zip_mb: String(Math.max(0, num(s.backup_max_zip_mb))),
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
        tab: "backup",
        backup_interval_minutes: num(form.backup_interval_minutes),
        backup_telegram_chat_id: num(form.backup_telegram_chat_id),
        backup_bale_chat_id: num(form.backup_bale_chat_id),
        backup_send_telegram_admins: form.backup_send_telegram_admins ? 1 : 0,
        backup_send_bale_admins: form.backup_send_bale_admins ? 1 : 0,
        backup_send_telegram_channel: form.backup_send_telegram_channel ? 1 : 0,
        backup_send_bale_channel: form.backup_send_bale_channel ? 1 : 0,
        backup_store_on_site: form.backup_store_on_site ? 1 : 0,
        backup_site_retention_count: Math.max(1, Math.min(500, num(form.backup_site_retention_count))),
        backup_max_zip_mb: Math.max(0, num(form.backup_max_zip_mb)),
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

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {tp(labelKey)}
    </label>
  )

  return (
    <div className={cn("mx-auto max-w-xl space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("cardTitle")}</CardTitle>
          <CardDescription>{tp("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="b_int">{tp("intervalMinutes")}</Label>
            <Input
              id="b_int"
              type="number"
              min={5}
              value={form.backup_interval_minutes}
              onChange={(e) => setForm((f) => ({ ...f, backup_interval_minutes: e.target.value }))}
            />
            <p className="text-xs text-muted-foreground">{tp("intervalHint", { min: formatNumber(5, isFa) })}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="b_tg">{tp("telegramChatId")}</Label>
            <Input
              id="b_tg"
              type="number"
              value={form.backup_telegram_chat_id}
              onChange={(e) => setForm((f) => ({ ...f, backup_telegram_chat_id: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="b_bl">{tp("baleChatId")}</Label>
            <Input
              id="b_bl"
              type="number"
              value={form.backup_bale_chat_id}
              onChange={(e) => setForm((f) => ({ ...f, backup_bale_chat_id: e.target.value }))}
            />
          </div>
          <div className="space-y-3 border-t border-border pt-3">
            {chk("backup_send_telegram_admins", "sendTelegramAdmins")}
            {chk("backup_send_bale_admins", "sendBaleAdmins")}
            {chk("backup_send_telegram_channel", "sendTelegramChannel")}
            {chk("backup_send_bale_channel", "sendBaleChannel")}
          </div>
          <div className="space-y-3 border-t border-border pt-3">
            <p className="text-sm font-medium">{tp("siteStorageTitle")}</p>
            {chk("backup_store_on_site", "storeOnSite")}
            <div className="space-y-2">
              <Label htmlFor="b_ret">{tp("retentionCount")}</Label>
              <Input
                id="b_ret"
                type="number"
                min={1}
                max={500}
                value={form.backup_site_retention_count}
                onChange={(e) => setForm((f) => ({ ...f, backup_site_retention_count: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("retentionHint")}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="b_maxmb">{tp("maxZipMb")}</Label>
              <Input
                id="b_maxmb"
                type="number"
                min={0}
                value={form.backup_max_zip_mb}
                onChange={(e) => setForm((f) => ({ ...f, backup_max_zip_mb: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("maxZipMbHint")}</p>
            </div>
          </div>
          {error ? (
            <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {error}
            </div>
          ) : null}
          <Button type="button" disabled={saving} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}

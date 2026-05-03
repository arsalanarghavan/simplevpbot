"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { BOT_PLATFORMS, type BotPlatformForm } from "@/config/bot-platforms"
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

export function DashboardBotsAdmin({
  settings,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`botsAdmin.${k}`)
  const s = settings ?? {}

  const initial = useMemo(
    () =>
      ({
        telegram_token: String(s.telegram_token ?? ""),
        bale_token: String(s.bale_token ?? ""),
        telegram_webhook_secret: String(s.telegram_webhook_secret ?? ""),
        bale_webhook_secret: String(s.bale_webhook_secret ?? ""),
        telegram_secret_header: String(s.telegram_secret_header ?? ""),
        bale_wallet_provider_token: String(s.bale_wallet_provider_token ?? ""),
      }) satisfies BotPlatformForm,
    [s]
  )

  const [form, setForm] = useState<BotPlatformForm>(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    setOkMsg(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "bots",
        ...form,
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      setOkMsg(tp("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const fieldInput = (key: keyof BotPlatformForm, labelKey: string, type: "text" | "password" = "password") => (
    <div className="space-y-2" key={key}>
      <Label htmlFor={key}>{tp(labelKey)}</Label>
      <Input
        id={key}
        type={type}
        autoComplete="off"
        value={form[key]}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
        placeholder={tp("placeholderSecret")}
      />
    </div>
  )

  return (
    <div className={cn("mx-auto max-w-3xl space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
        <p className="mt-1 text-xs text-muted-foreground">{tp("subtitleFuture")}</p>
        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
          <span>
            {tp("enabled")}: {String(s.enabled ?? "—")}
          </span>
          <span>
            {tp("webhookRate")}: {formatNumber(num(s.webhook_rate_limit_per_min), isFa)}
          </span>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {BOT_PLATFORMS.map((plat) => (
          <Card key={plat.id}>
            <CardHeader>
              <CardTitle className="text-base">{t(plat.titleKey)}</CardTitle>
              <CardDescription>{tp("cardDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="text-sm">
                <span className="text-muted-foreground">{t(plat.summaryUsernameKey)}</span>:{" "}
                {plat.id === "telegram"
                  ? String(s.telegram_bot_username ?? "—")
                  : String(s.bale_bot_username ?? "—")}
              </div>
              {plat.fieldKeys.map((fk) => {
                const labelMap: Partial<Record<keyof BotPlatformForm, string>> = {
                  telegram_token: "telegramToken",
                  bale_token: "baleToken",
                  telegram_webhook_secret: "telegramWebhookSecret",
                  bale_webhook_secret: "baleWebhookSecret",
                  telegram_secret_header: "telegramSecretHeader",
                  bale_wallet_provider_token: "baleWalletToken",
                }
                const lk = labelMap[fk] ?? String(fk)
                const inputType = fk === "telegram_secret_header" ? "text" : "password"
                return fieldInput(fk, lk, inputType)
              })}
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("saveCardTitle")}</CardTitle>
          <CardDescription>{tp("saveCardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {error ? (
            <div
              role="alert"
              className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
              {error}
            </div>
          ) : null}
          {okMsg ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}
          <Button type="button" disabled={saving} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}

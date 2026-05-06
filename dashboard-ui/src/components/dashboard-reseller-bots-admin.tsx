"use client"

import { useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"

export type ResellerBotProfile = {
  has_telegram_token?: boolean
  has_bale_token?: boolean
  brand_name?: string
  has_webhook_secret?: boolean
  webhook_telegram_url?: string
  webhook_bale_url?: string
}

export function DashboardResellerBotsAdmin({
  profile,
  isFa,
  onMutateSuccess,
}: {
  profile: ResellerBotProfile | null | undefined
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`resellerBotsAdmin.${k}`)
  const [tg, setTg] = useState("")
  const [bl, setBl] = useState("")
  const [brand, setBrand] = useState("")
  const [busy, setBusy] = useState(false)
  const [busyWh, setBusyWh] = useState<"" | "telegram" | "bale">("")
  const [busySecret, setBusySecret] = useState(false)
  const [msg, setMsg] = useState("")

  useEffect(() => {
    setBrand(profile?.brand_name ?? "")
  }, [profile?.brand_name])

  async function save() {
    setBusy(true)
    setMsg("")
    try {
      const res = await postAdminMutate("reseller_bot_tokens_save", {
        telegram_token: tg,
        bale_token: bl,
        brand_name: brand,
      })
      if (!res.ok) {
        setMsg(res.message || tp("error"))
        return
      }
      setMsg(tp("saved"))
      setTg("")
      setBl("")
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function registerWebhook(platform: "telegram" | "bale") {
    setBusyWh(platform)
    setMsg("")
    try {
      const res = await postAdminMutate("reseller_bot_webhook_set", { platform })
      if (!res.ok) {
        setMsg(res.message || tp("webhookError"))
        return
      }
      setMsg(tp("webhookOk"))
      onMutateSuccess?.()
    } finally {
      setBusyWh("")
    }
  }

  async function rotateWebhookSecret() {
    setBusySecret(true)
    setMsg("")
    try {
      const res = await postAdminMutate("reseller_bot_secret_rotate", {})
      if (!res.ok) {
        setMsg(res.message || tp("webhookError"))
        return
      }
      setMsg(isFa ? "سکرت وبهوک با موفقیت چرخانده شد." : "Webhook secret rotated.")
      onMutateSuccess?.()
    } finally {
      setBusySecret(false)
    }
  }

  function copyText(text: string) {
    void navigator.clipboard.writeText(text)
    setMsg(tp("copied"))
  }

  return (
    <div className="mx-auto max-w-lg space-y-6">
      <div>
        <h2 className="text-lg font-semibold tracking-tight">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("cardTitle")}</CardTitle>
          <CardDescription>
            {tp("status")}{" "}
            {profile?.has_telegram_token ? tp("yesTg") : tp("noTg")} ·{" "}
            {profile?.has_bale_token ? tp("yesBl") : tp("noBl")}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="brand-name">{tp("brandName")}</Label>
            <Input
              id="brand-name"
              value={brand}
              onChange={(e) => setBrand(e.target.value)}
              placeholder={tp("brandPlaceholder")}
              autoComplete="off"
            />
            <p className="text-xs text-muted-foreground">{tp("brandHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="tg-tok">{tp("tg")}</Label>
            <Input
              id="tg-tok"
              dir="ltr"
              type="password"
              autoComplete="off"
              value={tg}
              onChange={(e) => setTg(e.target.value)}
              placeholder="••••"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="bl-tok">{tp("bale")}</Label>
            <Input
              id="bl-tok"
              dir="ltr"
              type="password"
              autoComplete="off"
              value={bl}
              onChange={(e) => setBl(e.target.value)}
              placeholder="••••"
            />
          </div>
          <Button type="button" disabled={busy} onClick={() => void save()}>
            {tp("save")}
          </Button>
          {msg ? (
            <p className="text-sm whitespace-pre-wrap" dir={isFa ? "rtl" : "ltr"}>
              {msg}
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("webhookCardTitle")}</CardTitle>
          <CardDescription>{tp("webhookCardHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {profile?.webhook_telegram_url ? (
            <div className="space-y-1">
              <Label>{tp("webhookTgUrl")}</Label>
              <pre className="text-xs break-all rounded-md border bg-muted/40 p-2" dir="ltr">
                {profile.webhook_telegram_url}
              </pre>
              <Button type="button" variant="outline" size="sm" onClick={() => copyText(profile.webhook_telegram_url!)}>
                {tp("copyUrl")}
              </Button>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">{tp("webhookUrlPending")}</p>
          )}
          {profile?.webhook_bale_url ? (
            <div className="space-y-1">
              <Label>{tp("webhookBaleUrl")}</Label>
              <pre className="text-xs break-all rounded-md border bg-muted/40 p-2" dir="ltr">
                {profile.webhook_bale_url}
              </pre>
              <Button type="button" variant="outline" size="sm" onClick={() => copyText(profile.webhook_bale_url!)}>
                {tp("copyUrl")}
              </Button>
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={busyWh !== "" || !profile?.has_telegram_token}
              onClick={() => void registerWebhook("telegram")}
            >
              {busyWh === "telegram" ? "…" : tp("registerWebhookTg")}
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={busyWh !== "" || !profile?.has_bale_token}
              onClick={() => void registerWebhook("bale")}
            >
              {busyWh === "bale" ? "…" : tp("registerWebhookBale")}
            </Button>
            <Button type="button" variant="outline" disabled={busySecret} onClick={() => void rotateWebhookSecret()}>
              {busySecret ? "…" : isFa ? "چرخش سکرت وبهوک" : "Rotate webhook secret"}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

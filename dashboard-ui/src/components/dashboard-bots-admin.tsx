"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  EllipsisVertical,
  MessagesSquare,
  Pencil,
  Power,
  RefreshCw,
  Send,
  Trash2,
  Webhook,
} from "lucide-react"

import { AdminIdChips } from "@/components/dashboard-bots-admin-ids"
import { DashboardForceJoinAdmin } from "@/components/dashboard-force-join-admin"
import { BOT_PLATFORMS, type BotPlatformForm } from "@/config/bot-platforms"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Separator } from "@/components/ui/separator"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>
type BotRow = {
  reseller_id?: number
  reseller_name?: string
  reseller_status?: string
  brand_name?: string
  logo_url?: string
  favicon_url?: string
  theme_primary?: string
  theme_accent?: string
  custom_domain?: string
  enabled?: boolean
  has_telegram_token?: boolean
  has_bale_token?: boolean
  telegram_secret_token_set?: boolean
  webhook_telegram_url?: string
  webhook_bale_url?: string
  admin_telegram_ids?: number[]
  admin_bale_ids?: number[]
  text_overrides?: Record<string, string>
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function asIdList(v: unknown): number[] {
  if (!Array.isArray(v)) return []
  return v.map((x) => num(x)).filter((x) => x > 0)
}

export function DashboardBotsAdmin({
  settings,
  botsList = [],
  botsPagination,
  isFa,
  resellerSelfServe = false,
  onPageChange,
  onPerPageChange,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  isFa: boolean
  resellerSelfServe?: boolean
  botsList?: BotRow[]
  botsPagination?: PaginationMeta | null
  onPageChange?: (p: number) => void
  onPerPageChange?: (n: number) => void
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
        telegram_secret_header: String(s.telegram_secret_header ?? ""),
        bale_wallet_provider_token: String(s.bale_wallet_provider_token ?? ""),
      }) satisfies BotPlatformForm,
    [s]
  )

  const [form, setForm] = useState<BotPlatformForm>(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])

  const mainTgIds = asIdList(s.admin_telegram_ids)
  const mainBaleIds = asIdList(s.admin_bale_ids)

  const [saving, setSaving] = useState(false)
  const [busyAction, setBusyAction] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)
  const [dlgOpen, setDlgOpen] = useState(false)
  const [dlgRow, setDlgRow] = useState<BotRow | null>(null)
  const [dlgForm, setDlgForm] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!dlgOpen || !dlgRow) return
    const updated = botsList.find((r) => num(r.reseller_id) === num(dlgRow.reseller_id))
    if (updated) setDlgRow(updated)
  }, [botsList, dlgOpen, dlgRow])
  const [deleteHookDlg, setDeleteHookDlg] = useState<{
    platform: "telegram" | "bale"
    botId: number
  } | null>(null)

  const busy = busyAction !== "" || saving

  const runBotAction = useCallback(
    async (op: string, payload: Record<string, unknown>): Promise<boolean> => {
      setBusyAction(op)
      setError(null)
      setOkMsg(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setError(res.message || tp("saveError"))
          return false
        }
        if (op === "bot_test_telegram" || op === "bot_test_bale") {
          setOkMsg(tp("testOk"))
        } else if (op === "bot_delete_webhook" || op === "reseller_bot_webhook_delete") {
          setOkMsg(tp("webhookDeleted"))
        } else {
          setOkMsg(tp("saved"))
        }
        onMutateSuccess?.()
        return true
      } finally {
        setBusyAction("")
      }
    },
    [onMutateSuccess, tp]
  )

  const onSaveTokens = useCallback(async () => {
    setSaving(true)
    setError(null)
    setOkMsg(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "bots",
        telegram_token: form.telegram_token,
        bale_token: form.bale_token,
        telegram_secret_header: form.telegram_secret_header,
        bale_wallet_provider_token: form.bale_wallet_provider_token,
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

  const mainEnabled = Boolean(s.enabled)
  const tgUser = String(s.telegram_bot_username ?? "")
  const baleUser = String(s.bale_bot_username ?? "")

  const openResellerDlg = (row: BotRow) => {
    const ov = row.text_overrides ?? {}
    setDlgRow(row)
    setDlgForm({
      reseller_svp_user_id: String(row.reseller_id ?? 0),
      brand_name: String(row.brand_name ?? ""),
      logo_url: String(row.logo_url ?? ""),
      favicon_url: String(row.favicon_url ?? ""),
      theme_primary: String(row.theme_primary ?? ""),
      theme_accent: String(row.theme_accent ?? ""),
      custom_domain: String(row.custom_domain ?? ""),
      telegram_token: "",
      bale_token: "",
      bale_wallet_provider_token: "",
      text_msg_welcome: String(ov["msg.welcome"] ?? ""),
      text_btn_support_contact: String(ov["btn.support.contact"] ?? ""),
      text_btn_support_faq: String(ov["btn.support.faq"] ?? ""),
    })
    setDlgOpen(true)
  }

  const fieldInput = (key: keyof BotPlatformForm, labelKey: string, type: "text" | "password" = "password") => (
    <div className="space-y-1.5" key={key}>
      <Label htmlFor={key} className="text-xs">
        {tp(labelKey)}
      </Label>
      <Input
        id={key}
        type={type}
        autoComplete="off"
        className="h-9"
        value={form[key]}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
        placeholder={type === "password" ? tp("placeholderSecret") : ""}
        disabled={busy}
      />
    </div>
  )

  const webhookPayload = (platform: "telegram" | "bale", botId: number) =>
    resellerSelfServe && botId < 1
      ? { platform }
      : botId < 1
        ? { platform, bot_id: 0 }
        : { platform, bot_id: botId }

  const setWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_set" : "bot_set_webhook"

  const deleteWebhookOp = (botId: number) =>
    resellerSelfServe && botId < 1 ? "reseller_bot_webhook_delete" : "bot_delete_webhook"

  return (
    <div className={cn("mx-auto max-w-6xl space-y-4", isFa && "text-right")}>
      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {okMsg && !error ? (
        <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p>
      ) : null}

      {!resellerSelfServe ? (
        <Card>
          <CardHeader className="pb-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <CardTitle className="text-base">{tp("mainBotSectionTitle")}</CardTitle>
                <CardDescription className="text-xs">{tp("webhookSecretHint")}</CardDescription>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant={mainEnabled ? "default" : "secondary"}>
                  {mainEnabled ? tp("statusEnabled") : tp("statusDisabled")}
                </Badge>
                {tgUser ? (
                  <span className="text-xs text-muted-foreground" dir="ltr">
                    TG @{tgUser}
                  </span>
                ) : null}
                {baleUser ? (
                  <span className="text-xs text-muted-foreground" dir="ltr">
                    Bale @{baleUser}
                  </span>
                ) : null}
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className={cn("flex flex-wrap gap-2", isFa && "flex-row-reverse")}>
              <Button
                type="button"
                size="sm"
                variant={mainEnabled ? "destructive" : "default"}
                className="gap-1.5"
                disabled={busy}
                onClick={() => void runBotAction("bot_toggle_enabled", {})}
              >
                <Power className="size-3.5" />
                {mainEnabled ? tp("btnDisableBot") : tp("btnEnableBot")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={busy}
                onClick={() => void runBotAction("bot_test_telegram", {})}
              >
                <Send className="size-3.5" />
                {tp("testTelegramShort")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={busy}
                onClick={() => void runBotAction("bot_test_bale", {})}
              >
                <MessagesSquare className="size-3.5" />
                {tp("testBaleShort")}
              </Button>
              <Separator orientation="vertical" className="mx-1 hidden h-8 sm:block" />
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={busy}
                onClick={() => setDeleteHookDlg({ platform: "telegram", botId: 0 })}
              >
                <Trash2 className="size-3.5" />
                {tp("actionDeleteWebhookTg")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={busy}
                onClick={() => setDeleteHookDlg({ platform: "bale", botId: 0 })}
              >
                <Trash2 className="size-3.5" />
                {tp("actionDeleteWebhookBale")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                className="gap-1.5"
                disabled={busy}
                onClick={() => void runBotAction("bot_set_webhook", { platform: "telegram", bot_id: 0 })}
              >
                <Webhook className="size-3.5" />
                {tp("actionSetWebhookTg")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                className="gap-1.5"
                disabled={busy}
                onClick={() => void runBotAction("bot_set_webhook", { platform: "bale", bot_id: 0 })}
              >
                <Webhook className="size-3.5" />
                {tp("actionSetWebhookBale")}
              </Button>
            </div>
            <p className="text-xs text-muted-foreground">
              {tp("enabled")}: {String(s.enabled ?? "—")} · {tp("webhookRate")}:{" "}
              {formatNumber(num(s.webhook_rate_limit_per_min), isFa)}
            </p>

            <Separator />

            <div className="grid gap-4 md:grid-cols-2">
              <AdminIdChips
                platform="telegram"
                label={tp("adminTelegramIds")}
                ids={mainTgIds}
                isFa={isFa}
                busy={busy}
                tp={tp}
                onChanged={() => onMutateSuccess?.()}
                onError={setError}
              />
              <AdminIdChips
                platform="bale"
                label={tp("adminBaleIds")}
                ids={mainBaleIds}
                isFa={isFa}
                busy={busy}
                tp={tp}
                onChanged={() => onMutateSuccess?.()}
                onError={setError}
              />
            </div>
            <p className="text-xs text-muted-foreground">{tp("adminIdsCardDesc")}</p>

            <Separator />

            <div className="grid gap-4 md:grid-cols-2">
              {BOT_PLATFORMS.map((plat) => (
                <div key={plat.id} className="space-y-2 rounded-lg border border-border/60 p-3">
                  <p className="text-sm font-medium">{t(plat.titleKey)}</p>
                  <p className="text-xs text-muted-foreground">
                    {t(plat.summaryUsernameKey)}:{" "}
                    {plat.id === "telegram" ? tgUser || "—" : baleUser || "—"}
                  </p>
                  {plat.fieldKeys.map((fk) => {
                    const labelMap: Partial<Record<keyof BotPlatformForm, string>> = {
                      telegram_token: "telegramToken",
                      bale_token: "baleToken",
                      telegram_secret_header: "telegramSecretHeader",
                      bale_wallet_provider_token: "baleWalletToken",
                    }
                    const lk = labelMap[fk] ?? String(fk)
                    const inputType = fk === "telegram_secret_header" ? "text" : "password"
                    return fieldInput(fk, lk, inputType)
                  })}
                </div>
              ))}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3">
              <div>
                <p className="text-sm font-medium">{tp("saveTokens")}</p>
                <p className="text-xs text-muted-foreground">{tp("saveTokensDesc")}</p>
              </div>
              <Button type="button" size="sm" disabled={busy} onClick={() => void onSaveTokens()}>
                {tp("saveTokens")}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {!resellerSelfServe ? (
        <Card>
          <CardContent className="pt-6">
            <DashboardForceJoinAdmin
              settings={s}
              isFa={isFa}
              onMutateSuccess={onMutateSuccess}
            />
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">{tp("resellerBots")}</CardTitle>
          <CardDescription className="text-xs">{tp("resellerBotsDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[40rem] text-sm">
              <thead>
                <tr className="bg-muted/40 text-xs">
                  <th className="p-2">#</th>
                  <th className="p-2">{tp("resellerColReseller")}</th>
                  <th className="p-2">{tp("resellerColBrand")}</th>
                  <th className="p-2">{tp("colTgShort")}</th>
                  <th className="p-2">{tp("colBaleShort")}</th>
                  <th className="p-2">{tp("resellerColStatus")}</th>
                  <th className="p-2 w-12">{tp("moreActions")}</th>
                </tr>
              </thead>
              <tbody>
                {botsList.map((row, idx) => {
                  const rid = num(row.reseller_id)
                  return (
                    <tr key={`${rid}-${idx}`} className="border-t">
                      <td className="p-2 font-mono text-xs">{rid}</td>
                      <td className="p-2">{row.reseller_name || "—"}</td>
                      <td className="p-2">{row.brand_name || "—"}</td>
                      <td className="p-2">
                        <Badge variant={row.has_telegram_token ? "default" : "outline"} className="text-xs">
                          {row.has_telegram_token ? "✓" : "—"}
                        </Badge>
                      </td>
                      <td className="p-2">
                        <Badge variant={row.has_bale_token ? "default" : "outline"} className="text-xs">
                          {row.has_bale_token ? "✓" : "—"}
                        </Badge>
                      </td>
                      <td className="p-2">
                        <Badge variant={row.enabled ? "default" : "secondary"} className="text-xs">
                          {row.enabled ? tp("statusEnabled") : tp("statusDisabled")}
                        </Badge>
                      </td>
                      <td className="p-2">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy}>
                              <EllipsisVertical className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align={isFa ? "start" : "end"}>
                            <DropdownMenuItem onClick={() => openResellerDlg(row)}>
                              <Pencil className="size-4" />
                              {tp("actionEdit")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction("bot_reseller_toggle_enabled", { reseller_svp_user_id: rid })
                              }
                            >
                              <Power className="size-4" />
                              {tp("actionToggle")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              disabled={!row.has_telegram_token}
                              onClick={() =>
                                void runBotAction("bot_test_telegram", { reseller_svp_user_id: rid })
                              }
                            >
                              <Send className="size-4" />
                              {tp("testTelegramShort")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              disabled={!row.has_bale_token}
                              onClick={() => void runBotAction("bot_test_bale", { reseller_svp_user_id: rid })}
                            >
                              <MessagesSquare className="size-4" />
                              {tp("testBaleShort")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction(setWebhookOp(rid), webhookPayload("telegram", rid))
                              }
                            >
                              <Webhook className="size-4" />
                              {tp("actionSetWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction(setWebhookOp(rid), webhookPayload("bale", rid))
                              }
                            >
                              <Webhook className="size-4" />
                              {tp("actionSetWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setDeleteHookDlg({ platform: "telegram", botId: rid })}
                            >
                              <Trash2 className="size-4" />
                              {tp("actionDeleteWebhookTg")}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => setDeleteHookDlg({ platform: "bale", botId: rid })}
                            >
                              <Trash2 className="size-4" />
                              {tp("actionDeleteWebhookBale")}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              onClick={() =>
                                void runBotAction("bot_reseller_secret_rotate", { reseller_svp_user_id: rid })
                              }
                            >
                              <RefreshCw className="size-4" />
                              {tp("actionRotateSecret")}
                            </DropdownMenuItem>
                            {!resellerSelfServe ? (
                              <DropdownMenuItem
                                className="text-destructive focus:text-destructive"
                                onClick={() =>
                                  void runBotAction("bot_reseller_delete", { reseller_svp_user_id: rid })
                                }
                              >
                                <Trash2 className="size-4" />
                                {tp("actionDelete")}
                              </DropdownMenuItem>
                            ) : null}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          <DataPagination
            meta={botsPagination ?? null}
            isFa={isFa}
            onPageChange={(p) => onPageChange?.(p)}
            onPerPageChange={(n) => onPerPageChange?.(n)}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>

      <Dialog open={deleteHookDlg !== null} onOpenChange={(o) => !o && setDeleteHookDlg(null)}>
        <DialogContent className={cn("sm:max-w-md", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {deleteHookDlg?.platform === "bale" ? tp("actionDeleteWebhookBale") : tp("actionDeleteWebhookTg")}
            </DialogTitle>
            <DialogDescription>{tp("confirmDeleteWebhook")}</DialogDescription>
          </DialogHeader>
          <DialogFooter className={cn("gap-2", isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setDeleteHookDlg(null)} disabled={busy}>
              {tp("adminIdCancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={busy}
              onClick={() => {
                if (!deleteHookDlg) return
                const { platform, botId } = deleteHookDlg
                void runBotAction(deleteWebhookOp(botId), webhookPayload(platform, botId)).then((ok) => {
                  if (ok) setDeleteHookDlg(null)
                })
              }}
            >
              {deleteHookDlg?.platform === "bale" ? tp("actionDeleteWebhookBale") : tp("actionDeleteWebhookTg")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={dlgOpen} onOpenChange={setDlgOpen}>
        <DialogContent className={cn("sm:max-w-lg max-h-[90vh] overflow-y-auto", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{tp("resellerDialogTitle")}</DialogTitle>
            <DialogDescription className="text-xs">{tp("resellerWebhookAutoHint")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="space-y-1">
              <Label className="text-xs">{tp("resellerPlaceholderId")}</Label>
              <Input dir="ltr" value={dlgForm.reseller_svp_user_id ?? ""} readOnly disabled className="h-9 font-mono" />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("resellerPlaceholderBrand")}</Label>
              <Input
                value={dlgForm.brand_name ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, brand_name: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <Input
              placeholder={tp("brandingLogoUrl")}
              value={dlgForm.logo_url ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, logo_url: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            <Input
              placeholder={tp("brandingFaviconUrl")}
              value={dlgForm.favicon_url ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, favicon_url: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            <div className="grid grid-cols-2 gap-2">
              <Input
                placeholder={tp("brandingThemePrimary")}
                value={dlgForm.theme_primary ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_primary: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
              <Input
                placeholder={tp("brandingThemeAccent")}
                value={dlgForm.theme_accent ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, theme_accent: e.target.value }))}
                dir="ltr"
                disabled={busy}
              />
            </div>
            <Input
              placeholder={tp("brandingCustomDomain")}
              value={dlgForm.custom_domain ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, custom_domain: e.target.value }))}
              dir="ltr"
              disabled={busy}
            />
            <Input
              placeholder={tp("dlgPhTelegramToken")}
              value={dlgForm.telegram_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, telegram_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={tp("dlgPhBaleToken")}
              value={dlgForm.bale_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <Input
              placeholder={tp("dlgPhBaleWallet")}
              value={dlgForm.bale_wallet_provider_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_wallet_provider_token: e.target.value }))}
              type="password"
              autoComplete="off"
              disabled={busy}
            />
            <div className="space-y-1">
              <Label className="text-xs">{tp("textWelcomeOverride")}</Label>
              <Textarea
                value={dlgForm.text_msg_welcome ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_msg_welcome: e.target.value }))}
                disabled={busy}
                rows={4}
                className="text-sm"
              />
              <p className="text-[11px] text-muted-foreground">{tp("textWelcomeHint")}</p>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("textSupportContactOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_contact ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_contact: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{tp("textSupportFaqOverride")}</Label>
              <Input
                value={dlgForm.text_btn_support_faq ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, text_btn_support_faq: e.target.value }))}
                disabled={busy}
                className="h-9"
              />
            </div>
            {dlgRow ? (
              <>
                <AdminIdChips
                  platform="telegram"
                  label={tp("adminTelegramIds")}
                  ids={dlgRow.admin_telegram_ids ?? []}
                  resellerId={num(dlgRow.reseller_id)}
                  isFa={isFa}
                  busy={busy}
                  tp={tp}
                  onChanged={() => onMutateSuccess?.()}
                  onError={setError}
                />
                <AdminIdChips
                  platform="bale"
                  label={tp("adminBaleIds")}
                  ids={dlgRow.admin_bale_ids ?? []}
                  resellerId={num(dlgRow.reseller_id)}
                  isFa={isFa}
                  busy={busy}
                  tp={tp}
                  onChanged={() => onMutateSuccess?.()}
                  onError={setError}
                />
              </>
            ) : null}
          </div>
          <DialogFooter className={cn("gap-2", isFa && "flex-row-reverse")}>
            <Button variant="outline" onClick={() => setDlgOpen(false)} disabled={busy}>
              {tp("adminIdCancel")}
            </Button>
            <Button
              disabled={busy}
              onClick={() => {
                void runBotAction("bot_reseller_save", {
                  ...dlgForm,
                  reseller_svp_user_id: Number(dlgForm.reseller_svp_user_id || "0"),
                  enabled: dlgRow?.enabled !== false,
                  text_overrides: {
                    "msg.welcome": String(dlgForm.text_msg_welcome ?? ""),
                    "btn.support.contact": String(dlgForm.text_btn_support_contact ?? ""),
                    "btn.support.faq": String(dlgForm.text_btn_support_faq ?? ""),
                  },
                }).then((ok) => {
                  if (ok) setDlgOpen(false)
                })
              }}
            >
              {tp("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

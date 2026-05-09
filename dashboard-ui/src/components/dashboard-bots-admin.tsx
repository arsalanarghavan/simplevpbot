"use client"

import type { ReactNode } from "react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  MessagesSquare,
  Pencil,
  Power,
  RefreshCw,
  Send,
  Trash2,
  Webhook,
} from "lucide-react"

import { BOT_PLATFORMS, type BotPlatformForm } from "@/config/bot-platforms"
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
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
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
  enabled?: boolean
  has_telegram_token?: boolean
  has_bale_token?: boolean
  telegram_secret_token_set?: boolean
  webhook_telegram_url?: string
  webhook_bale_url?: string
  admin_telegram_ids?: number[]
  admin_bale_ids?: number[]
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function BotIconButton({
  label,
  disabled,
  onClick,
  variant = "secondary",
  children,
}: {
  label: string
  disabled?: boolean
  onClick: () => void
  variant?: "default" | "secondary" | "destructive" | "outline"
  children: ReactNode
}) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="inline-flex">
          <Button
            type="button"
            size="icon"
            variant={variant}
            disabled={disabled}
            aria-label={label}
            onClick={onClick}
          >
            {children}
          </Button>
        </span>
      </TooltipTrigger>
      <TooltipContent side="bottom">
        <p>{label}</p>
      </TooltipContent>
    </Tooltip>
  )
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
  /** Only reseller bot profile (hide main site bot / tokens). */
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

  const [adminTg, setAdminTg] = useState("")
  const [adminBale, setAdminBale] = useState("")
  useEffect(() => {
    const tg = Array.isArray(s.admin_telegram_ids) ? (s.admin_telegram_ids as number[]).join("\n") : ""
    const bl = Array.isArray(s.admin_bale_ids) ? (s.admin_bale_ids as number[]).join("\n") : ""
    setAdminTg(tg)
    setAdminBale(bl)
  }, [s])

  const [saving, setSaving] = useState(false)
  const [busyAction, setBusyAction] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)
  const [dlgOpen, setDlgOpen] = useState(false)
  const [dlgForm, setDlgForm] = useState<Record<string, string>>({})

  const busy = busyAction !== ""

  const onSave = useCallback(async () => {
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
        admin_telegram_ids: adminTg,
        admin_bale_ids: adminBale,
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
  }, [adminBale, adminTg, form, onMutateSuccess, tp])

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

  const fieldInput = (key: keyof BotPlatformForm, labelKey: string, type: "text" | "password" = "password") => (
    <div className="space-y-2" key={key}>
      <Label htmlFor={key}>{tp(labelKey)}</Label>
      <Input
        id={key}
        type={type}
        autoComplete="off"
        value={form[key]}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
        placeholder={type === "password" ? tp("placeholderSecret") : ""}
      />
    </div>
  )

  const mainEnabled = Boolean(s.enabled)

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      {resellerSelfServe && error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      {resellerSelfServe && okMsg && !error ? (
        <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p>
      ) : null}
      {!resellerSelfServe ? (
        <Card className="mx-auto w-full max-w-3xl">
          <CardHeader>
            <CardTitle className="text-lg">{tp("mainBotSectionTitle")}</CardTitle>
            <CardDescription className="text-pretty">{tp("mainBotSectionDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <TooltipProvider delayDuration={300}>
              <div
                className={cn(
                  "flex flex-wrap items-center gap-1",
                  isFa && "flex-row-reverse"
                )}
              >
                <BotIconButton
                  label={mainEnabled ? tp("btnDisableBot") : tp("btnEnableBot")}
                  variant={mainEnabled ? "destructive" : "default"}
                  disabled={busy}
                  onClick={() => void runBotAction("bot_toggle_enabled", {})}
                >
                  <Power className="size-4" />
                </BotIconButton>
                <BotIconButton
                  label={tp("testTelegram")}
                  disabled={busy}
                  onClick={() => void runBotAction("bot_test_telegram", {})}
                >
                  <Send className="size-4" />
                </BotIconButton>
                <BotIconButton
                  label={tp("testBale")}
                  disabled={busy}
                  onClick={() => void runBotAction("bot_test_bale", {})}
                >
                  <MessagesSquare className="size-4" />
                </BotIconButton>
                <BotIconButton
                  label={tp("actionTgHook")}
                  disabled={busy}
                  onClick={() => void runBotAction("bot_set_webhook", { platform: "telegram" })}
                >
                  <Webhook className="size-4" />
                </BotIconButton>
                <BotIconButton
                  label={tp("actionBaleHook")}
                  disabled={busy}
                  onClick={() => void runBotAction("bot_set_webhook", { platform: "bale" })}
                >
                  <Webhook className="size-4" />
                </BotIconButton>
              </div>
            </TooltipProvider>
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span>
                {tp("enabled")}: {String(s.enabled ?? "—")}
              </span>
              <span>
                {tp("webhookRate")}: {formatNumber(num(s.webhook_rate_limit_per_min), isFa)}
              </span>
            </div>

            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-base">{tp("adminIdsCardTitle")}</CardTitle>
                <CardDescription>{tp("adminIdsCardDesc")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="space-y-2">
                  <Label htmlFor="admin_tg">{tp("adminTelegramIds")}</Label>
                  <textarea
                    id="admin_tg"
                    className="min-h-24 w-full rounded-md border bg-background p-2 text-sm"
                    value={adminTg}
                    onChange={(e) => setAdminTg(e.target.value)}
                    placeholder={tp("dlgPhAdminTgIds")}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="admin_bale">{tp("adminBaleIds")}</Label>
                  <textarea
                    id="admin_bale"
                    className="min-h-24 w-full rounded-md border bg-background p-2 text-sm"
                    value={adminBale}
                    onChange={(e) => setAdminBale(e.target.value)}
                    placeholder={tp("dlgPhAdminBaleIds")}
                  />
                </div>
              </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
              {BOT_PLATFORMS.map((plat) => (
                <Card key={plat.id}>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">{t(plat.titleKey)}</CardTitle>
                    <CardDescription>{tp("cardDescTokens")}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3">
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
              <CardHeader className="pb-3">
                <CardTitle className="text-base">{tp("saveCardTitle")}</CardTitle>
                <CardDescription>{tp("saveCardDesc")}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
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
          </CardContent>
        </Card>
      ) : null}

      <Card className="w-full">
        <CardHeader>
          <CardTitle className="text-base">{tp("resellerBots")}</CardTitle>
          <CardDescription>{tp("resellerBotsDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <TooltipProvider delayDuration={300}>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full min-w-[56rem] text-sm">
                <thead>
                  <tr className="bg-muted/40">
                    <th className="p-2">#</th>
                    <th className="p-2">{tp("resellerColReseller")}</th>
                    <th className="p-2">{tp("resellerColBrand")}</th>
                    <th className="p-2">{tp("colTgShort")}</th>
                    <th className="p-2">{tp("colBaleShort")}</th>
                    <th className="p-2">{tp("resellerColStatus")}</th>
                    <th className="min-w-[14rem] whitespace-nowrap p-2">{tp("resellerColActions")}</th>
                  </tr>
                </thead>
                <tbody>
                  {botsList.map((row, idx) => (
                    <tr key={`${row.reseller_id ?? "r"}-${idx}`} className="border-t">
                      <td className="p-2 font-mono">{row.reseller_id ?? 0}</td>
                      <td className="p-2">{row.reseller_name || "—"}</td>
                      <td className="p-2">{row.brand_name || "—"}</td>
                      <td className="p-2">{row.has_telegram_token ? "✓" : "—"}</td>
                      <td className="p-2">{row.has_bale_token ? "✓" : "—"}</td>
                      <td className="p-2">{row.enabled ? tp("statusEnabled") : tp("statusDisabled")}</td>
                      <td className="p-2">
                        <div
                          className={cn(
                            "flex flex-nowrap items-center gap-1",
                            isFa && "flex-row-reverse"
                          )}
                        >
                          <BotIconButton
                            label={tp("actionEdit")}
                            variant="outline"
                            disabled={busy}
                            onClick={() => {
                              setDlgForm({
                                reseller_svp_user_id: String(row.reseller_id ?? 0),
                                brand_name: String(row.brand_name ?? ""),
                                admin_telegram_ids: (row.admin_telegram_ids ?? []).join("\n"),
                                admin_bale_ids: (row.admin_bale_ids ?? []).join("\n"),
                                enabled: row.enabled ? "1" : "0",
                              })
                              setDlgOpen(true)
                            }}
                          >
                            <Pencil className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("actionToggle")}
                            disabled={busy}
                            onClick={() =>
                              void runBotAction("bot_reseller_toggle_enabled", {
                                reseller_svp_user_id: row.reseller_id ?? 0,
                              })
                            }
                          >
                            <Power className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("testTelegramShort")}
                            disabled={busy || !row.has_telegram_token}
                            onClick={() =>
                              void runBotAction("bot_test_telegram", {
                                reseller_svp_user_id: row.reseller_id ?? 0,
                              })
                            }
                          >
                            <Send className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("testBaleShort")}
                            disabled={busy || !row.has_bale_token}
                            onClick={() =>
                              void runBotAction("bot_test_bale", {
                                reseller_svp_user_id: row.reseller_id ?? 0,
                              })
                            }
                          >
                            <MessagesSquare className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("actionTgHook")}
                            disabled={busy}
                            onClick={() =>
                              void runBotAction(
                                resellerSelfServe ? "reseller_bot_webhook_set" : "bot_set_webhook",
                                resellerSelfServe
                                  ? { platform: "telegram" }
                                  : { bot_id: row.reseller_id ?? 0, platform: "telegram" }
                              )
                            }
                          >
                            <Webhook className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("actionBaleHook")}
                            disabled={busy}
                            onClick={() =>
                              void runBotAction(
                                resellerSelfServe ? "reseller_bot_webhook_set" : "bot_set_webhook",
                                resellerSelfServe
                                  ? { platform: "bale" }
                                  : { bot_id: row.reseller_id ?? 0, platform: "bale" }
                              )
                            }
                          >
                            <Webhook className="size-4" />
                          </BotIconButton>
                          <BotIconButton
                            label={tp("actionRotateSecret")}
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                              void runBotAction("bot_reseller_secret_rotate", {
                                reseller_svp_user_id: row.reseller_id ?? 0,
                              })
                            }
                          >
                            <RefreshCw className="size-4" />
                          </BotIconButton>
                          {!resellerSelfServe ? (
                            <BotIconButton
                              label={tp("actionDelete")}
                              variant="destructive"
                              disabled={busy}
                              onClick={() =>
                                void runBotAction("bot_reseller_delete", {
                                  reseller_svp_user_id: row.reseller_id ?? 0,
                                })
                              }
                            >
                              <Trash2 className="size-4" />
                            </BotIconButton>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </TooltipProvider>
          <DataPagination
            meta={botsPagination ?? null}
            isFa={isFa}
            onPageChange={(p) => onPageChange?.(p)}
            onPerPageChange={(n) => onPerPageChange?.(n)}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>

      <Dialog open={dlgOpen} onOpenChange={setDlgOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{tp("resellerDialogTitle")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3">
            <Input
              placeholder={tp("resellerPlaceholderId")}
              value={dlgForm.reseller_svp_user_id ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, reseller_svp_user_id: e.target.value }))}
              readOnly
            />
            <Input
              placeholder={tp("resellerPlaceholderBrand")}
              value={dlgForm.brand_name ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, brand_name: e.target.value }))}
            />
            <Input
              placeholder={tp("dlgPhTelegramToken")}
              value={dlgForm.telegram_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, telegram_token: e.target.value }))}
              type="password"
              autoComplete="off"
            />
            <Input
              placeholder={tp("dlgPhBaleToken")}
              value={dlgForm.bale_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_token: e.target.value }))}
              type="password"
              autoComplete="off"
            />
            <Input
              placeholder={tp("dlgPhBaleWallet")}
              value={dlgForm.bale_wallet_provider_token ?? ""}
              onChange={(e) => setDlgForm((p) => ({ ...p, bale_wallet_provider_token: e.target.value }))}
              type="password"
              autoComplete="off"
            />
            <div className="space-y-2">
              <Label>{tp("adminTelegramIds")}</Label>
              <textarea
                className="min-h-20 w-full rounded-md border p-2 text-sm"
                placeholder={tp("dlgPhAdminTgIds")}
                value={dlgForm.admin_telegram_ids ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, admin_telegram_ids: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("adminBaleIds")}</Label>
              <textarea
                className="min-h-20 w-full rounded-md border p-2 text-sm"
                placeholder={tp("dlgPhAdminBaleIds")}
                value={dlgForm.admin_bale_ids ?? ""}
                onChange={(e) => setDlgForm((p) => ({ ...p, admin_bale_ids: e.target.value }))}
              />
            </div>
            <p className="text-xs text-muted-foreground">{tp("resellerWebhookAutoHint")}</p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDlgOpen(false)}>
              {t("a11y.close")}
            </Button>
            <Button
              onClick={() => {
                void runBotAction("bot_reseller_save", {
                  ...dlgForm,
                  reseller_svp_user_id: Number(dlgForm.reseller_svp_user_id || "0"),
                  enabled: dlgForm.enabled !== "0",
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

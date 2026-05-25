"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"

import { ColorHexField } from "@/components/site-settings/color-hex-field"
import { ImageUrlField } from "@/components/site-settings/image-url-field"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>
type WpPage = { id: number; title: string }

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function linesFromIds(raw: unknown): string {
  if (Array.isArray(raw)) return (raw as unknown[]).map(String).join("\n")
  return String(raw ?? "")
}

export function SiteSettingsWhitelabelTab({
  settings,
  wpPages,
  plans,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  wpPages: WpPage[]
  plans: DashRecord[]
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`siteSettings.whitelabel.${k}`)
  const s = settings ?? {}
  const supportRef = useRef<HTMLDivElement>(null)

  const initial = useMemo(
    () => ({
      enabled: bool(s.enabled ?? true),
      test_account_enabled: bool(s.test_account_enabled),
      crisis_mode: bool(s.crisis_mode),
      suppress_bulk_user_notifications: bool(s.suppress_bulk_user_notifications),
      portal_page_id: String(Number(s.portal_page_id) || 0),
      default_service_plan_id: String(Number(s.default_service_plan_id) || 0),
      default_bot_locale: String(s.default_bot_locale || "fa"),
      cards_display_mode: String(s.cards_display_mode || "list"),
      dashboard_site_name: String(s.dashboard_site_name ?? ""),
      dashboard_site_icon_url: String(s.dashboard_site_icon_url ?? ""),
      branding_logo_url: String(s.branding_logo_url ?? ""),
      branding_favicon_url: String(s.branding_favicon_url ?? ""),
      branding_theme_primary: String(s.branding_theme_primary ?? ""),
      branding_theme_accent: String(s.branding_theme_accent ?? ""),
      branding_custom_domain: String(s.branding_custom_domain ?? ""),
      support_info: String(s.support_info ?? ""),
      support_telegram_username: String(s.support_telegram_username ?? ""),
      support_bale_username: String(s.support_bale_username ?? ""),
      admin_telegram_ids: linesFromIds(s.admin_telegram_ids),
      admin_bale_ids: linesFromIds(s.admin_bale_ids),
      receipt_reject_reasons: Array.isArray(s.receipt_reject_reasons)
        ? (s.receipt_reject_reasons as string[]).join("\n")
        : "",
    }),
    [s],
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (typeof window === "undefined") return
    if (window.location.hash !== "#whitelabel-support") return
    const el = supportRef.current
    if (!el) return
    const id = window.setTimeout(() => {
      el.scrollIntoView({ behavior: "smooth", block: "start" })
    }, 150)
    return () => window.clearTimeout(id)
  }, [])

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "whitelabel",
        enabled: form.enabled ? 1 : 0,
        test_account_enabled: form.test_account_enabled ? 1 : 0,
        crisis_mode: form.crisis_mode ? 1 : 0,
        suppress_bulk_user_notifications: form.suppress_bulk_user_notifications ? 1 : 0,
        portal_page_id: Number(form.portal_page_id) || 0,
        default_service_plan_id: Number(form.default_service_plan_id) || 0,
        default_bot_locale: form.default_bot_locale,
        cards_display_mode: form.cards_display_mode,
        dashboard_site_name: form.dashboard_site_name,
        dashboard_site_icon_url: form.dashboard_site_icon_url,
        branding_logo_url: form.branding_logo_url,
        branding_favicon_url: form.branding_favicon_url,
        branding_theme_primary: form.branding_theme_primary,
        branding_theme_accent: form.branding_theme_accent,
        branding_custom_domain: form.branding_custom_domain,
        support_info: form.support_info,
        support_telegram_username: form.support_telegram_username,
        support_bale_username: form.support_bale_username,
        admin_telegram_ids: form.admin_telegram_ids,
        admin_bale_ids: form.admin_bale_ids,
        receipt_reject_reasons: form.receipt_reject_reasons.split(/\r?\n/).map((x) => x.trim()).filter(Boolean),
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

  const row = cn("flex items-center justify-between gap-3", isFa && "flex-row-reverse")

  return (
    <div dir={isFa ? "rtl" : "ltr"} className={cn("mx-auto max-w-6xl space-y-6", isFa && "text-right")}>
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("brandingTitle")}</CardTitle>
            <CardDescription>{tp("brandingDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="site_name">{tp("siteName")}</Label>
              <Input
                id="site_name"
                value={form.dashboard_site_name}
                onChange={(e) => setForm((f) => ({ ...f, dashboard_site_name: e.target.value }))}
                placeholder={tp("siteNamePlaceholder")}
              />
            </div>
            <ImageUrlField
              id="site_icon"
              label={tp("siteIconUrl")}
              value={form.dashboard_site_icon_url}
              onChange={(url) => setForm((f) => ({ ...f, dashboard_site_icon_url: url }))}
              rtl={isFa}
              onUploadError={(msg) => setError(msg || tp("uploadError"))}
            />
            <ImageUrlField
              id="logo_url"
              label={tp("logoUrl")}
              value={form.branding_logo_url}
              onChange={(url) => setForm((f) => ({ ...f, branding_logo_url: url }))}
              rtl={isFa}
              onUploadError={(msg) => setError(msg || tp("uploadError"))}
            />
            <ImageUrlField
              id="favicon_url"
              label={tp("faviconUrl")}
              value={form.branding_favicon_url}
              onChange={(url) => setForm((f) => ({ ...f, branding_favicon_url: url }))}
              rtl={isFa}
              onUploadError={(msg) => setError(msg || tp("uploadError"))}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <ColorHexField
                id="theme_primary"
                label={tp("themePrimary")}
                value={form.branding_theme_primary}
                onChange={(v) => setForm((f) => ({ ...f, branding_theme_primary: v }))}
                fallback="#2563eb"
                rtl={isFa}
              />
              <ColorHexField
                id="theme_accent"
                label={tp("themeAccent")}
                value={form.branding_theme_accent}
                onChange={(v) => setForm((f) => ({ ...f, branding_theme_accent: v }))}
                fallback="#7c3aed"
                rtl={isFa}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="custom_domain">{tp("customDomain")}</Label>
              <Input
                id="custom_domain"
                value={form.branding_custom_domain}
                onChange={(e) => setForm((f) => ({ ...f, branding_custom_domain: e.target.value }))}
                placeholder="panel.example.com"
                dir="ltr"
                className="font-mono text-left"
              />
              <p className="text-xs text-muted-foreground">{tp("customDomainHint")}</p>
            </div>
            <div className="space-y-2">
              <Label>{tp("defaultLocale")}</Label>
              <Select
                value={form.default_bot_locale}
                onValueChange={(v) => setForm((f) => ({ ...f, default_bot_locale: v }))}
              >
                <SelectTrigger className="w-full" dir={isFa ? "rtl" : "ltr"}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent dir={isFa ? "rtl" : "ltr"}>
                  <SelectItem value="fa">{tp("localeFa")}</SelectItem>
                  <SelectItem value="en">{tp("localeEn")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("generalTitle")}</CardTitle>
            <CardDescription>{tp("generalDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className={row}>
              <Label>{tp("enabled")}</Label>
              <Switch checked={form.enabled} onCheckedChange={(v) => setForm((f) => ({ ...f, enabled: v }))} />
            </div>
            <div className={row}>
              <Label>{tp("testAccount")}</Label>
              <Switch
                checked={form.test_account_enabled}
                onCheckedChange={(v) => setForm((f) => ({ ...f, test_account_enabled: v }))}
              />
            </div>
            <div className={row}>
              <Label>{tp("crisisMode")}</Label>
              <Switch checked={form.crisis_mode} onCheckedChange={(v) => setForm((f) => ({ ...f, crisis_mode: v }))} />
            </div>
            <div className={row}>
              <Label>{tp("suppressBulk")}</Label>
              <Switch
                checked={form.suppress_bulk_user_notifications}
                onCheckedChange={(v) => setForm((f) => ({ ...f, suppress_bulk_user_notifications: v }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("portalPage")}</Label>
              <Select
                value={form.portal_page_id}
                onValueChange={(v) => setForm((f) => ({ ...f, portal_page_id: v }))}
              >
                <SelectTrigger className="w-full" dir={isFa ? "rtl" : "ltr"}>
                  <SelectValue placeholder={tp("portalPageNone")} />
                </SelectTrigger>
                <SelectContent dir={isFa ? "rtl" : "ltr"}>
                  <SelectItem value="0">{tp("portalPageNone")}</SelectItem>
                  {wpPages.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{tp("defaultPlan")}</Label>
              <Select
                value={form.default_service_plan_id}
                onValueChange={(v) => setForm((f) => ({ ...f, default_service_plan_id: v }))}
              >
                <SelectTrigger className="w-full" dir={isFa ? "rtl" : "ltr"}>
                  <SelectValue placeholder={tp("defaultPlanNone")} />
                </SelectTrigger>
                <SelectContent dir={isFa ? "rtl" : "ltr"}>
                  <SelectItem value="0">{tp("defaultPlanNone")}</SelectItem>
                  {plans.map((p) => {
                    const id = Number(p.id)
                    if (!Number.isFinite(id) || id < 1) return null
                    const label = String(p.label_fa || p.label_en || p.slug || id)
                    return (
                      <SelectItem key={id} value={String(id)}>
                        {label}
                      </SelectItem>
                    )
                  })}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{tp("cardsMode")}</Label>
              <Select
                value={form.cards_display_mode}
                onValueChange={(v) => setForm((f) => ({ ...f, cards_display_mode: v }))}
              >
                <SelectTrigger className="w-full" dir={isFa ? "rtl" : "ltr"}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent dir={isFa ? "rtl" : "ltr"}>
                  <SelectItem value="list">{tp("cardsList")}</SelectItem>
                  <SelectItem value="sequential">{tp("cardsSequential")}</SelectItem>
                  <SelectItem value="random">{tp("cardsRandom")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("adminsTitle")}</CardTitle>
            <CardDescription>{tp("adminsDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="adm_tg">{tp("adminTelegramIds")}</Label>
              <Textarea
                id="adm_tg"
                value={form.admin_telegram_ids}
                onChange={(e) => setForm((f) => ({ ...f, admin_telegram_ids: e.target.value }))}
                rows={3}
                dir="ltr"
                className="font-mono text-left"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="adm_bl">{tp("adminBaleIds")}</Label>
              <Textarea
                id="adm_bl"
                value={form.admin_bale_ids}
                onChange={(e) => setForm((f) => ({ ...f, admin_bale_ids: e.target.value }))}
                rows={3}
                dir="ltr"
                className="font-mono text-left"
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("receiptsTitle")}</CardTitle>
            <CardDescription>{tp("receiptsDesc")}</CardDescription>
          </CardHeader>
          <CardContent>
            <Textarea
              value={form.receipt_reject_reasons}
              onChange={(e) => setForm((f) => ({ ...f, receipt_reject_reasons: e.target.value }))}
              rows={6}
            />
          </CardContent>
        </Card>

        <div id="whitelabel-support" ref={supportRef} className="lg:col-span-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("supportTitle")}</CardTitle>
            <CardDescription>{tp("supportDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 lg:grid-cols-2">
            <div className="space-y-2 lg:col-span-2">
              <Label htmlFor="support_info">{tp("supportInfo")}</Label>
              <Textarea
                id="support_info"
                value={form.support_info}
                onChange={(e) => setForm((f) => ({ ...f, support_info: e.target.value }))}
                rows={4}
                placeholder={tp("supportInfoPlaceholder")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="support_tg">{tp("supportTelegramUsername")}</Label>
              <Input
                id="support_tg"
                value={form.support_telegram_username}
                onChange={(e) => setForm((f) => ({ ...f, support_telegram_username: e.target.value }))}
                placeholder="username"
                dir="ltr"
                className="font-mono text-left"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="support_bl">{tp("supportBaleUsername")}</Label>
              <Input
                id="support_bl"
                value={form.support_bale_username}
                onChange={(e) => setForm((f) => ({ ...f, support_bale_username: e.target.value }))}
                placeholder="username"
                dir="ltr"
                className="font-mono text-left"
              />
            </div>
          </CardContent>
        </Card>
        </div>
      </div>

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}
      <div className={cn("flex", isFa && "justify-end")}>
        <Button type="button" disabled={saving} onClick={() => void onSave()}>
          {tp("save")}
        </Button>
      </div>
    </div>
  )
}

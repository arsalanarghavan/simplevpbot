"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

const PERM_KEYS = [
  "users.manage",
  "users.bulk",
  "broadcast.send",
  "receipts.review",
  "plans.manage",
  "services.manage",
] as const

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function labelForReseller(r: DashRecord): string {
  const fn = String(r.first_name ?? "").trim()
  const ln = String(r.last_name ?? "").trim()
  const name = `${fn} ${ln}`.trim()
  const un = String(r.username ?? "").trim()
  const id = Number(r.svp_user_id ?? r.id)
  if (name) return un ? `${name} (@${un})` : name
  if (un) return `@${un}`
  return `#${id}`
}

export function SiteSettingsResellersTab({
  settings,
  resellers,
  resellerPermissionsMap,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  resellers: DashRecord[]
  resellerPermissionsMap: Record<string, Record<string, boolean>>
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`siteSettings.resellers.${k}`)
  const tr = (k: string) => t(`resellersAdmin.${k}`)

  const permLabels: Record<string, string> = useMemo(
    () => ({
      "users.manage": tr("perm_users_manage"),
      "users.bulk": tr("perm_users_bulk"),
      "broadcast.send": tr("perm_broadcast_send"),
      "receipts.review": tr("perm_receipts_review"),
      "plans.manage": tr("perm_plans_manage"),
      "services.manage": tr("perm_services_manage"),
    }),
    [tr],
  )

  const defaultFromSettings = useMemo(() => {
    const raw = settings?.default_reseller_permissions
    const out: Record<string, boolean> = {}
    for (const k of PERM_KEYS) {
      if (raw && typeof raw === "object" && k in (raw as Record<string, unknown>)) {
        out[k] = bool((raw as Record<string, unknown>)[k])
      } else {
        out[k] = true
      }
    }
    return out
  }, [settings])

  const [defaults, setDefaults] = useState(defaultFromSettings)
  useEffect(() => setDefaults(defaultFromSettings), [defaultFromSettings])

  const [selectedId, setSelectedId] = useState<string>("")
  const [editPerms, setEditPerms] = useState<Record<string, boolean>>({})
  const [savingDefaults, setSavingDefaults] = useState(false)
  const [savingReseller, setSavingReseller] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const id = Number(selectedId)
    if (!Number.isFinite(id) || id < 1) {
      setEditPerms({})
      return
    }
    const map = resellerPermissionsMap[String(id)] ?? resellerPermissionsMap[id]
    const out: Record<string, boolean> = {}
    for (const k of PERM_KEYS) {
      out[k] = map && k in map ? bool(map[k]) : bool(defaults[k])
    }
    setEditPerms(out)
  }, [selectedId, resellerPermissionsMap, defaults])

  const row = cn("flex items-center justify-between gap-3", isFa && "flex-row-reverse")

  const saveDefaults = useCallback(async () => {
    setSavingDefaults(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "resellers_defaults",
        default_reseller_permissions: defaults,
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setSavingDefaults(false)
    }
  }, [defaults, onMutateSuccess, tp])

  const saveReseller = useCallback(async () => {
    const id = Number(selectedId)
    if (!Number.isFinite(id) || id < 1) return
    setSavingReseller(true)
    setError(null)
    try {
      const res = await postAdminMutate("reseller_permissions_save", {
        reseller_svp_user_id: id,
        permissions: editPerms,
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      onMutateSuccess?.()
    } finally {
      setSavingReseller(false)
    }
  }, [selectedId, editPerms, onMutateSuccess, tp])

  const permSwitches = (perms: Record<string, boolean>, setPerms: (p: Record<string, boolean>) => void) =>
    PERM_KEYS.map((k) => (
      <div key={k} className={row}>
        <Label>{permLabels[k] ?? k}</Label>
        <Switch
          checked={Boolean(perms[k])}
          onCheckedChange={(v) => setPerms({ ...perms, [k]: v })}
        />
      </div>
    ))

  return (
    <div className={cn("mx-auto max-w-2xl space-y-6", isFa && "text-right")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("defaultsTitle")}</CardTitle>
          <CardDescription>{tp("defaultsDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {permSwitches(defaults, setDefaults)}
          <Button type="button" disabled={savingDefaults} onClick={() => void saveDefaults()}>
            {tp("saveDefaults")}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("editTitle")}</CardTitle>
          <CardDescription>{tp("editDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>{tp("pickReseller")}</Label>
            <Select value={selectedId || "none"} onValueChange={(v) => setSelectedId(v === "none" ? "" : v)}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={tp("pickPlaceholder")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{tp("pickPlaceholder")}</SelectItem>
                {resellers.map((r) => {
                  const id = Number(r.svp_user_id ?? r.id)
                  if (!Number.isFinite(id) || id < 1) return null
                  return (
                    <SelectItem key={id} value={String(id)}>
                      {labelForReseller(r)}
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>
          </div>
          {selectedId ? (
            <>
              <div className="space-y-3">{permSwitches(editPerms, setEditPerms)}</div>
              <Button type="button" disabled={savingReseller} onClick={() => void saveReseller()}>
                {tp("saveReseller")}
              </Button>
            </>
          ) : null}
        </CardContent>
      </Card>

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}
    </div>
  )
}

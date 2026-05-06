"use client"

import { useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { DataPagination } from "@/components/data-pagination"
import type { PaginationMeta } from "@/lib/dash-pagination"

type DashRecord = Record<string, unknown>

function n(v: unknown): number {
  const x = Number(v)
  return Number.isFinite(x) ? x : 0
}

function displayName(u: DashRecord): string {
  const name = `${String(u.first_name ?? "").trim()} ${String(u.last_name ?? "").trim()}`.trim()
  return name || String(u.username ?? "").trim() || "—"
}

export function DashboardResellersAdmin({
  rows,
  panels,
  resellerPermissionsMap,
  resellerPanelPricesMap,
  isFa,
  pagination,
  onPageChange,
  onPerPageChange,
  onOpenUserDetail,
  onMutateSuccess,
}: {
  rows: DashRecord[]
  panels: DashRecord[]
  resellerPermissionsMap?: Record<string, Record<string, boolean> | undefined>
  resellerPanelPricesMap?: Record<string, Array<{ panel_id?: number; price_per_gb?: number | string }> | undefined>
  isFa: boolean
  pagination: PaginationMeta | null
  onPageChange: (p: number) => void
  onPerPageChange: (n: number) => void
  onOpenUserDetail: (id: number) => void
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`resellersAdmin.${k}`)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    username: "",
    phone: "",
    tg_user_id: "",
    bale_user_id: "",
    wp_user_id: "",
  })
  const [wpForm, setWpForm] = useState({
    wp_username: "",
    wp_password: "",
    email: "",
  })
  const [priceResellerId, setPriceResellerId] = useState<number | null>(null)
  const [priceRows, setPriceRows] = useState<{ panel_id: number; price_per_gb: string }[]>([])
  const [permResellerId, setPermResellerId] = useState<number | null>(null)
  const [permissions, setPermissions] = useState<Record<string, boolean>>({})

  const directUsersCount = useMemo(() => {
    const m = new Map<number, number>()
    for (const r of rows) {
      m.set(n(r.id), n(r.direct_users_count))
    }
    return m
  }, [rows])

  function openPriceDialog(rid: number) {
    setPriceResellerId(rid)
    const existingRows = resellerPanelPricesMap?.[String(rid)] ?? []
    const existingByPanel = new Map<number, string>()
    for (const row of existingRows) {
      const pid = n(row?.panel_id)
      if (pid > 0) existingByPanel.set(pid, String(row?.price_per_gb ?? ""))
    }
    setPriceRows(
      panels.map((p) => ({
        panel_id: n(p.id),
        price_per_gb: existingByPanel.get(n(p.id)) ?? "",
      }))
    )
  }

  async function savePrices() {
    if (priceResellerId == null) return
    setBusy(true)
    setErr("")
    try {
      const rows = priceRows
        .map((r) => ({
          panel_id: r.panel_id,
          price_per_gb: parseFloat(r.price_per_gb.replace(/,/g, ".")) || 0,
        }))
        .filter((r) => r.price_per_gb > 0)
      const res = await postAdminMutate("reseller_panel_prices_save", {
        reseller_svp_user_id: priceResellerId,
        rows,
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setPriceResellerId(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function createReseller() {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("user_manual_create", {
        role: "reseller",
        status: "approved",
        ...form,
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setForm({
        first_name: "",
        last_name: "",
        username: "",
        phone: "",
        tg_user_id: "",
        bale_user_id: "",
        wp_user_id: "",
      })
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function createWithWp() {
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("reseller_wp_provision", {
        svp_user_id: 0,
        first_name: form.first_name,
        last_name: form.last_name,
        username: form.username,
        phone: form.phone,
        wp_username: wpForm.wp_username.trim(),
        wp_password: wpForm.wp_password,
        email: wpForm.email.trim(),
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setWpForm({ wp_username: "", wp_password: "", email: "" })
      setForm({
        first_name: "",
        last_name: "",
        username: "",
        phone: "",
        tg_user_id: "",
        bale_user_id: "",
        wp_user_id: "",
      })
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const permDefs = [
    { key: "users.manage", label: isFa ? "مدیریت کاربران" : "Manage users" },
    { key: "users.merge", label: isFa ? "ادغام کاربران" : "Merge users" },
    { key: "users.bulk", label: isFa ? "عملیات گروهی" : "Bulk operations" },
    { key: "broadcast.send", label: isFa ? "پیام همگانی" : "Broadcast" },
    { key: "receipts.review", label: isFa ? "بررسی رسیدها" : "Review receipts" },
    { key: "plans.manage", label: isFa ? "مدیریت پلن‌ها" : "Manage plans" },
    { key: "services.manage", label: isFa ? "مدیریت سرویس‌ها" : "Manage services" },
  ]

  async function savePermissions() {
    if (permResellerId == null) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("reseller_permissions_save", {
        reseller_svp_user_id: permResellerId,
        permissions,
      })
      if (!res.ok) {
        setErr(res.message || tp("createError"))
        return
      }
      setPermResellerId(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-medium">{tp("title")}</h2>
      <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("createTitle")}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-2">
          <Input placeholder={tp("firstName")} value={form.first_name} onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))} />
          <Input placeholder={tp("lastName")} value={form.last_name} onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))} />
          <Input placeholder={tp("username")} value={form.username} onChange={(e) => setForm((p) => ({ ...p, username: e.target.value }))} />
          <Input placeholder={tp("phone")} value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} />
          <Input placeholder={tp("tgUserId")} value={form.tg_user_id} onChange={(e) => setForm((p) => ({ ...p, tg_user_id: e.target.value }))} />
          <Input placeholder={tp("baleUserId")} value={form.bale_user_id} onChange={(e) => setForm((p) => ({ ...p, bale_user_id: e.target.value }))} />
          <Input placeholder={tp("wpUserId")} value={form.wp_user_id} onChange={(e) => setForm((p) => ({ ...p, wp_user_id: e.target.value }))} />
          <div className="md:col-span-2 flex flex-wrap items-center gap-2">
            <Button type="button" disabled={busy} onClick={() => void createReseller()}>
              {tp("create")}
            </Button>
            {err ? <span className="text-sm text-destructive">{err}</span> : null}
          </div>
        </CardContent>
      </Card>

      <Card className="border-primary/20 bg-gradient-to-br from-primary/[0.06] to-transparent">
        <CardHeader>
          <CardTitle className="text-base">{tp("wpLoginTitle")}</CardTitle>
          <CardDescription>{tp("panelPricesHint")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-2">
          <Input placeholder={tp("wpUsername")} dir="ltr" value={wpForm.wp_username} onChange={(e) => setWpForm((p) => ({ ...p, wp_username: e.target.value }))} />
          <Input placeholder={tp("wpPassword")} dir="ltr" type="password" value={wpForm.wp_password} onChange={(e) => setWpForm((p) => ({ ...p, wp_password: e.target.value }))} />
          <Input placeholder={tp("email")} dir="ltr" type="email" className="md:col-span-2" value={wpForm.email} onChange={(e) => setWpForm((p) => ({ ...p, email: e.target.value }))} />
          <div className="md:col-span-2">
            <Button type="button" variant="default" disabled={busy} onClick={() => void createWithWp()}>
              {tp("createWithLogin")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("listTitle")}</CardTitle>
        </CardHeader>
        <CardContent>
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("empty")}</p>
          ) : (
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full min-w-[44rem] text-sm">
                <thead>
                  <tr className="bg-muted/40">
                    <th className="p-2">{tp("colId")}</th>
                    <th className="p-2">{tp("colName")}</th>
                    <th className="p-2">{tp("colStatus")}</th>
                    <th className="p-2">{tp("colUsers")}</th>
                    <th className="p-2">{tp("colActions")}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => {
                    const id = n(r.id)
                    return (
                      <tr key={id} className="border-t">
                        <td className="p-2 font-mono" dir="ltr">
                          {id}
                        </td>
                        <td className="p-2">{displayName(r)}</td>
                        <td className="p-2">{String(r.status ?? "")}</td>
                        <td className="p-2">{directUsersCount.get(id) ?? 0}</td>
                        <td className="p-2">
                          <div className="flex flex-wrap gap-1">
                            <Button type="button" variant="outline" size="sm" onClick={() => onOpenUserDetail(id)}>
                              {tp("manage")}
                            </Button>
                            <Button type="button" variant="secondary" size="sm" onClick={() => openPriceDialog(id)} disabled={panels.length < 1}>
                              {tp("panelPrices")}
                            </Button>
                            <Button
                              type="button"
                              variant="secondary"
                              size="sm"
                              onClick={() => {
                                setPermResellerId(id)
                                setPermissions({ ...(resellerPermissionsMap?.[String(id)] ?? {}) })
                              }}
                            >
                              {isFa ? "سطح دسترسی" : "Permissions"}
                            </Button>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
          <DataPagination meta={pagination} isFa={isFa} onPageChange={onPageChange} onPerPageChange={onPerPageChange} />
        </CardContent>
      </Card>

      <Dialog open={priceResellerId != null} onOpenChange={(o) => !o && setPriceResellerId(null)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{tp("panelPricesTitle")}</DialogTitle>
            <DialogDescription>
              #{priceResellerId} — {tp("panelPricesHint")}
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            {priceRows.map((row, idx) => {
              const pl = panels.find((p) => n(p.id) === row.panel_id)
              const label = String(pl?.label ?? `Panel ${row.panel_id}`)
              return (
                <div key={row.panel_id} className="grid gap-1">
                  <Label htmlFor={`ppb-${row.panel_id}`}>{label}</Label>
                  <Input
                    id={`ppb-${row.panel_id}`}
                    dir="ltr"
                    value={priceRows[idx]?.price_per_gb ?? ""}
                    onChange={(e) => {
                      const v = e.target.value
                      setPriceRows((prev) => prev.map((r, i) => (i === idx ? { ...r, price_per_gb: v } : r)))
                    }}
                    placeholder="0"
                  />
                </div>
              )
            })}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setPriceResellerId(null)}>
              {isFa ? "بستن" : "Close"}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void savePrices()}>
              {tp("panelPricesSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <Dialog open={permResellerId != null} onOpenChange={(o) => !o && setPermResellerId(null)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{isFa ? "سطح دسترسی نماینده" : "Reseller permissions"}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {permDefs.map((p) => (
              <label key={p.key} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={permissions[p.key] !== false}
                  onChange={(e) => setPermissions((prev) => ({ ...prev, [p.key]: e.target.checked }))}
                />
                {p.label}
              </label>
            ))}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setPermResellerId(null)}>
              {isFa ? "بستن" : "Close"}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void savePermissions()}>
              {isFa ? "ذخیره دسترسی‌ها" : "Save permissions"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

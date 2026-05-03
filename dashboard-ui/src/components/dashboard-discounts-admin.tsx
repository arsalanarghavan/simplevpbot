"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { useCallback, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
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
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import {
  Sheet,
  SheetContent,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isDiscountActive(d: DashRecord): boolean {
  return d.active === true || d.active === 1 || d.active === "1"
}

type DiscountFormState = {
  svpc_id: number
  svpc_code: string
  svpc_type: "percent" | "fixed_toman"
  svpc_value: number
  svpc_max_uses: string
  svpc_valid_from: string
  svpc_valid_until: string
  svpc_min_order: string
  svpc_active: boolean
  svpc_allow_new: boolean
  svpc_allow_renew: boolean
  svpc_allow_vol: boolean
  svpc_allow_users: boolean
}

function emptyForm(): DiscountFormState {
  return {
    svpc_id: 0,
    svpc_code: "",
    svpc_type: "percent",
    svpc_value: 0,
    svpc_max_uses: "",
    svpc_valid_from: "",
    svpc_valid_until: "",
    svpc_min_order: "",
    svpc_active: true,
    svpc_allow_new: true,
    svpc_allow_renew: true,
    svpc_allow_vol: true,
    svpc_allow_users: true,
  }
}

function dtToInput(v: unknown): string {
  if (v == null || v === "") return ""
  const s = String(v).trim()
  if (!s) return ""
  return s.replace("T", " ").slice(0, 16)
}

function formFromRow(d: DashRecord): DiscountFormState {
  const maxu = d.max_uses
  return {
    svpc_id: num(d.id),
    svpc_code: String(d.code ?? ""),
    svpc_type: d.discount_type === "fixed_toman" ? "fixed_toman" : "percent",
    svpc_value: num(d.discount_value),
    svpc_max_uses: maxu == null || maxu === "" ? "" : String(maxu),
    svpc_valid_from: dtToInput(d.valid_from),
    svpc_valid_until: dtToInput(d.valid_until),
    svpc_min_order: d.min_order_toman == null || d.min_order_toman === "" ? "" : String(d.min_order_toman),
    svpc_active: isDiscountActive(d),
    svpc_allow_new: !!(d.allow_new_purchase === true || d.allow_new_purchase === 1 || d.allow_new_purchase === "1"),
    svpc_allow_renew: !!(d.allow_renew_same === true || d.allow_renew_same === 1 || d.allow_renew_same === "1"),
    svpc_allow_vol: !!(d.allow_add_volume === true || d.allow_add_volume === 1 || d.allow_add_volume === "1"),
    svpc_allow_users: !!(d.allow_add_user_slots === true || d.allow_add_user_slots === 1 || d.allow_add_user_slots === "1"),
  }
}

function formToPayload(f: DiscountFormState): Record<string, unknown> {
  return {
    svpc_id: f.svpc_id,
    svpc_code: f.svpc_code,
    svpc_type: f.svpc_type,
    svpc_value: f.svpc_value,
    svpc_max_uses: f.svpc_max_uses.trim(),
    svpc_valid_from: f.svpc_valid_from.trim(),
    svpc_valid_until: f.svpc_valid_until.trim(),
    svpc_min_order: f.svpc_min_order.trim(),
    svpc_active: f.svpc_active ? 1 : 0,
    svpc_allow_new: f.svpc_allow_new ? 1 : 0,
    svpc_allow_renew: f.svpc_allow_renew ? 1 : 0,
    svpc_allow_vol: f.svpc_allow_vol ? 1 : 0,
    svpc_allow_users: f.svpc_allow_users ? 1 : 0,
  }
}

const selectClass =
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

export function DashboardDiscountsAdmin({
  discountCodes,
  pagination,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  discountCodes: DashRecord[]
  pagination: PaginationMeta | null
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`discountsAdmin.${k}`)

  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<DiscountFormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const stats = useMemo(() => {
    let active = 0
    let percent = 0
    let fixed = 0
    for (const d of discountCodes) {
      if (isDiscountActive(d)) active += 1
      if (String(d.discount_type ?? "") === "fixed_toman") fixed += 1
      else percent += 1
    }
    return {
      total: pagination?.total ?? discountCodes.length,
      active,
      inactive: discountCodes.length - active,
      percent,
      fixed,
    }
  }, [discountCodes, pagination])

  const filtered = useMemo(() => {
    if (filter === "active") return discountCodes.filter(isDiscountActive)
    if (filter === "inactive") return discountCodes.filter((d) => !isDiscountActive(d))
    return discountCodes
  }, [discountCodes, filter])

  const openAdd = useCallback(() => {
    setError(null)
    setFormMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }, [])

  const openEdit = useCallback((d: DashRecord) => {
    setError(null)
    setFormMode("edit")
    setForm(formFromRow(d))
    setSheetOpen(true)
  }, [])

  const onSaveSheet = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = formToPayload(form)
      if (formMode === "add") {
        payload.svpc_id = 0
      }
      const res = await postAdminMutate("discount_save", payload)
      if (!res.ok) {
        setError(res.message || tp("mutateError"))
        return
      }
      setSheetOpen(false)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, formMode, onMutateSuccess, tp])

  const onConfirmDelete = useCallback(async () => {
    if (!deleteTarget) return
    const id = num(deleteTarget.id)
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("discount_delete", { svpc_delete_id: id })
      if (!res.ok) {
        setError(res.message || tp("mutateError"))
        return
      }
      setDeleteTarget(null)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [deleteTarget, onMutateSuccess, tp])

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.active, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsInactive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.inactive, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsPercent")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.percent, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsFixed")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.fixed, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      {pagination ? (
        <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <Label className="text-muted-foreground">{tp("filterLabel")}</Label>
          <select
            className={selectClass + " w-auto min-w-[8rem]"}
            value={filter}
            onChange={(e) => setFilter(e.target.value as typeof filter)}
          >
            <option value="all">{tp("filterAll")}</option>
            <option value="active">{tp("filterActive")}</option>
            <option value="inactive">{tp("filterInactive")}</option>
          </select>
        </div>
        <Button type="button" size="sm" onClick={openAdd}>
          {tp("addCode")}
        </Button>
      </div>

      <Separator />

      {filtered.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("empty")}</p>
      ) : (
        <ul className="space-y-3">
          {filtered.map((d) => {
            const id = num(d.id)
            const act = isDiscountActive(d)
            const dtype = String(d.discount_type ?? "percent")
            return (
              <li key={id}>
                <Card>
                  <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div className="min-w-0 space-y-1">
                      <CardTitle className="font-mono text-base">{String(d.code ?? "")}</CardTitle>
                      <CardDescription>
                        {dtype === "fixed_toman" ? tp("typeFixed") : tp("typePercent")} · {tp("value")}:{" "}
                        {formatNumber(num(d.discount_value), isFa)}
                        {" · "}
                        {tp("uses")}: {formatNumber(num(d.uses_count), isFa)}
                        {d.max_uses != null && d.max_uses !== ""
                          ? ` / ${formatNumber(num(d.max_uses), isFa)}`
                          : ` / ${tp("unlimited")}`}
                      </CardDescription>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      <Badge variant={act ? "default" : "secondary"}>{act ? tp("badgeActive") : tp("badgeInactive")}</Badge>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" className="size-8">
                            <EllipsisVerticalIcon className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align={isFa ? "start" : "end"}>
                          <DropdownMenuItem onClick={() => openEdit(d)}>{tp("edit")}</DropdownMenuItem>
                          <DropdownMenuItem className="text-destructive" onClick={() => setDeleteTarget(d)}>
                            {tp("delete")}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </CardHeader>
                  <CardContent className="text-xs text-muted-foreground">
                    <span>
                      {tp("validFrom")}: {formatDateTime(d.valid_from as string | undefined, isFa)}
                    </span>
                    {" · "}
                    <span>
                      {tp("validUntil")}: {formatDateTime(d.valid_until as string | undefined, isFa)}
                    </span>
                    <div className="mt-1 flex flex-wrap gap-x-2 gap-y-1">
                      <span>{flagLabel(d, "allow_new_purchase", tp("flagNew"))}</span>
                      <span>{flagLabel(d, "allow_renew_same", tp("flagRenew"))}</span>
                      <span>{flagLabel(d, "allow_add_volume", tp("flagVol"))}</span>
                      <span>{flagLabel(d, "allow_add_user_slots", tp("flagUsers"))}</span>
                    </div>
                  </CardContent>
                </Card>
              </li>
            )
          })}
        </ul>
      )}

      <DataPagination
        meta={pagination}
        isFa={isFa}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className={cn("flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md", isFa && "text-right")}>
          <SheetHeader className="border-b p-4 text-left rtl:text-right">
            <SheetTitle>{formMode === "add" ? tp("addCode") : tp("editCode")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 p-4">
            <div className="space-y-2">
              <Label>{tp("fieldCode")}</Label>
              <Input
                className="font-mono"
                disabled={formMode === "edit"}
                value={form.svpc_code}
                onChange={(e) => setForm((f) => ({ ...f, svpc_code: e.target.value }))}
              />
              {formMode === "edit" ? <p className="text-xs text-muted-foreground">{tp("codeLockedHint")}</p> : null}
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldType")}</Label>
              <select
                className={selectClass}
                value={form.svpc_type}
                onChange={(e) =>
                  setForm((f) => ({
                    ...f,
                    svpc_type: e.target.value === "fixed_toman" ? "fixed_toman" : "percent",
                  }))
                }
              >
                <option value="percent">{tp("typePercent")}</option>
                <option value="fixed_toman">{tp("typeFixed")}</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldValue")}</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={form.svpc_value}
                onChange={(e) => setForm((f) => ({ ...f, svpc_value: num(e.target.value) }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldMaxUses")}</Label>
              <Input
                placeholder={tp("placeholderUnlimited")}
                value={form.svpc_max_uses}
                onChange={(e) => setForm((f) => ({ ...f, svpc_max_uses: e.target.value }))}
              />
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{tp("fieldValidFrom")}</Label>
                <Input
                  placeholder="YYYY-MM-DD HH:MM"
                  value={form.svpc_valid_from}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_valid_from: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldValidUntil")}</Label>
                <Input
                  placeholder="YYYY-MM-DD HH:MM"
                  value={form.svpc_valid_until}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_valid_until: e.target.value }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldMinOrder")}</Label>
              <Input
                value={form.svpc_min_order}
                onChange={(e) => setForm((f) => ({ ...f, svpc_min_order: e.target.value }))}
              />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.svpc_active}
                onChange={(e) => setForm((f) => ({ ...f, svpc_active: e.target.checked }))}
              />
              {tp("active")}
            </label>
            <div className="space-y-2 border-t pt-2">
              <p className="text-xs font-medium text-muted-foreground">{tp("allowSection")}</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_new}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_new: e.target.checked }))}
                />
                {tp("flagNew")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_renew}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_renew: e.target.checked }))}
                />
                {tp("flagRenew")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_vol}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_vol: e.target.checked }))}
                />
                {tp("flagVol")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_users}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_users: e.target.checked }))}
                />
                {tp("flagUsers")}
              </label>
            </div>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void onSaveSheet()}>
              {tp("save")}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className={cn(isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDescription")}</DialogDescription>
          </DialogHeader>
          <DialogFooter className={cn("gap-2 sm:justify-between", isFa && "sm:flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void onConfirmDelete()}>
              {tp("deleteConfirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function flagLabel(d: DashRecord, key: string, label: string): string {
  const v = d[key]
  const on = v === true || v === 1 || v === "1"
  return `${label}: ${on ? "✓" : "—"}`
}

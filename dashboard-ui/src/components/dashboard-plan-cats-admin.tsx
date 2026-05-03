"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { useCallback, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
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
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(r: DashRecord): boolean {
  return r.active === true || r.active === 1 || r.active === "1"
}

const selectClass =
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

type FormState = {
  pc_id: number
  pc_label: string
  pc_slug: string
  pc_panel_id: number
  pc_sort: number
  pc_active: boolean
}

function emptyForm(defaultPanel: number): FormState {
  return {
    pc_id: 0,
    pc_label: "",
    pc_slug: "",
    pc_panel_id: defaultPanel,
    pc_sort: 0,
    pc_active: true,
  }
}

function formFromRow(r: DashRecord): FormState {
  return {
    pc_id: num(r.id),
    pc_label: String(r.label ?? ""),
    pc_slug: String(r.slug ?? ""),
    pc_panel_id: Math.max(1, num(r.panel_id) || 1),
    pc_sort: num(r.sort_order),
    pc_active: isActiveRow(r),
  }
}

export function DashboardPlanCatsAdmin({
  planCategories,
  panels,
  pagination,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  planCategories: DashRecord[]
  panels: DashRecord[]
  pagination: PaginationMeta | null
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`planCatsAdmin.${k}`)
  const defaultPanel = Math.max(1, num(panels[0]?.id) || 1)

  const [sheetOpen, setSheetOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<FormState>(() => emptyForm(defaultPanel))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const run = useCallback(
    async (params: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("plan_category", params)
        if (!res.ok) {
          setError(res.code || res.message || tp("mutateError"))
          return
        }
        setSheetOpen(false)
        setDeleteTarget(null)
        onMutateSuccess?.()
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, tp]
  )

  const openAdd = () => {
    setError(null)
    setMode("add")
    setForm(emptyForm(defaultPanel))
    setSheetOpen(true)
  }

  const openEdit = (r: DashRecord) => {
    setError(null)
    setMode("edit")
    setForm(formFromRow(r))
    setSheetOpen(true)
  }

  const onSave = () => {
    if (mode === "add") {
      void run({
        pc_action: "add",
        pc_label: form.pc_label.trim(),
        pc_slug: form.pc_slug.trim().toLowerCase().replace(/[^a-z0-9_]/g, ""),
        pc_panel_id: form.pc_panel_id,
        pc_sort: form.pc_sort,
        pc_active: form.pc_active ? 1 : 0,
      })
      return
    }
    void run({
      pc_action: "update",
      pc_id: form.pc_id,
      pc_label: form.pc_label.trim(),
      pc_sort: form.pc_sort,
      pc_active: form.pc_active ? 1 : 0,
    })
  }

  const panelOptions = useMemo(() => {
    return panels.map((p) => ({ id: num(p.id), label: String(p.label ?? `#${num(p.id)}`) }))
  }, [panels])

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      <div className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h2 className="text-lg font-medium">{tp("title")}</h2>
          <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
        </div>
        <Button type="button" size="sm" onClick={openAdd}>
          {tp("add")}
        </Button>
      </div>

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}

      {planCategories.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("empty")}</p>
      ) : (
        <div className="w-full max-w-full overflow-x-auto rounded-md border border-border">
          <table
            className={cn(
              "w-full min-w-[36rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              isFa ? "text-right" : "text-left"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">#</th>
                <th className="p-2 font-medium">{tp("colLabel")}</th>
                <th className="p-2 font-medium">slug</th>
                <th className="p-2 font-medium">{tp("colPanel")}</th>
                <th className="p-2 font-medium">{tp("colSort")}</th>
                <th className="p-2 font-medium">{tp("colActive")}</th>
                <th className="p-2 w-10" />
              </tr>
            </thead>
            <tbody>
              {planCategories.map((r) => {
                const id = num(r.id)
                const pid = num(r.panel_id)
                return (
                  <tr key={id}>
                    <td className="p-2 font-mono text-xs tabular-nums">{formatNumber(id, isFa)}</td>
                    <td className="p-2">{String(r.label ?? "")}</td>
                    <td className="p-2 font-mono text-xs">{String(r.slug ?? "")}</td>
                    <td className="p-2 tabular-nums">{formatNumber(pid, isFa)}</td>
                    <td className="p-2 tabular-nums">{formatNumber(num(r.sort_order), isFa)}</td>
                    <td className="p-2">
                      <Badge variant={isActiveRow(r) ? "default" : "secondary"}>
                        {isActiveRow(r) ? tp("active") : tp("inactive")}
                      </Badge>
                    </td>
                    <td className="p-2">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                            <EllipsisVerticalIcon className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align={isFa ? "start" : "end"}>
                          <DropdownMenuItem onClick={() => void run({ pc_action: "toggle", pc_id: id })}>
                            {tp("toggle")}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => openEdit(r)}>{tp("edit")}</DropdownMenuItem>
                          <DropdownMenuItem className="text-destructive" onClick={() => setDeleteTarget(r)}>
                            {tp("delete")}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <DataPagination
        meta={pagination}
        isFa={isFa}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className={cn("flex w-full flex-col sm:max-w-md", isFa && "text-right")} side={isFa ? "left" : "right"}>
          <SheetHeader>
            <SheetTitle>{mode === "add" ? tp("sheetAdd") : tp("sheetEdit")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 overflow-y-auto px-4 pb-4">
            <div className="space-y-2">
              <Label>{tp("fieldLabel")}</Label>
              <Input value={form.pc_label} onChange={(e) => setForm((f) => ({ ...f, pc_label: e.target.value }))} />
            </div>
            {mode === "add" ? (
              <>
                <div className="space-y-2">
                  <Label>{tp("fieldSlug")}</Label>
                  <Input
                    value={form.pc_slug}
                    onChange={(e) => setForm((f) => ({ ...f, pc_slug: e.target.value }))}
                    className="font-mono text-sm"
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("fieldPanel")}</Label>
                  <select
                    className={selectClass}
                    value={form.pc_panel_id}
                    onChange={(e) => setForm((f) => ({ ...f, pc_panel_id: num(e.target.value) }))}
                  >
                    {panelOptions.map((o) => (
                      <option key={o.id} value={o.id}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                </div>
              </>
            ) : null}
            <div className="space-y-2">
              <Label>{tp("fieldSort")}</Label>
              <Input
                type="number"
                value={form.pc_sort}
                onChange={(e) => setForm((f) => ({ ...f, pc_sort: num(e.target.value) }))}
              />
            </div>
            <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.pc_active}
                onChange={(e) => setForm((f) => ({ ...f, pc_active: e.target.checked }))}
              />
              {tp("fieldActive")}
            </label>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {tp("save")}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className={cn(isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDesc")}</DialogDescription>
          </DialogHeader>
          <DialogFooter className={cn("gap-2", isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={saving}
              onClick={() => deleteTarget && void run({ pc_action: "delete", pc_id: num(deleteTarget.id) })}
            >
              {tp("delete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

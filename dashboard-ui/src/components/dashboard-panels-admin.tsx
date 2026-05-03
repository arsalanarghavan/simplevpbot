"use client"

import { EllipsisVerticalIcon } from "lucide-react"
import { useCallback, useState } from "react"
import { useTranslation } from "react-i18next"

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
import { postAdminMutate, type AdminMutateResult } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber, formatNumericString } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(r: DashRecord): boolean {
  return r.active === true || r.active === 1 || r.active === "1"
}

type FormState = {
  xp_id: number
  xp_label: string
  xp_panel_url: string
  xp_panel_username: string
  xp_panel_password: string
  xp_panel_api_base: string
  xp_panel_login_secret: string
  xp_subscription_public_base: string
  xp_sort_order: number
  xp_active: boolean
}

function emptyForm(): FormState {
  return {
    xp_id: 0,
    xp_label: "",
    xp_panel_url: "",
    xp_panel_username: "",
    xp_panel_password: "",
    xp_panel_api_base: "panel/api",
    xp_panel_login_secret: "",
    xp_subscription_public_base: "",
    xp_sort_order: 0,
    xp_active: true,
  }
}

function formFromRow(r: DashRecord): FormState {
  return {
    xp_id: num(r.id),
    xp_label: String(r.label ?? ""),
    xp_panel_url: String(r.panel_url ?? ""),
    xp_panel_username: String(r.panel_username ?? ""),
    xp_panel_password: "",
    xp_panel_api_base: String(r.panel_api_base ?? "panel/api"),
    xp_panel_login_secret: String(r.panel_login_secret ?? ""),
    xp_subscription_public_base: String(r.subscription_public_base ?? ""),
    xp_sort_order: num(r.sort_order),
    xp_active: isActiveRow(r),
  }
}

export function DashboardPanelsAdmin({
  panels,
  pagination,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  panels: DashRecord[]
  pagination: PaginationMeta | null
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`panelsAdmin.${k}`, opts)

  const [sheetOpen, setSheetOpen] = useState(false)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [testOpen, setTestOpen] = useState(false)
  const [testLoading, setTestLoading] = useState(false)
  const [testPanelId, setTestPanelId] = useState(0)
  const [testRes, setTestRes] = useState<AdminMutateResult | null>(null)

  const run = useCallback(
    async (params: Record<string, unknown>) => {
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("panel_xp", params)
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

  const runPanelTest = useCallback(async (panelId: number) => {
    setTestPanelId(panelId)
    setTestOpen(true)
    setTestLoading(true)
    setTestRes(null)
    try {
      const res = await postAdminMutate("panel_test", { panel_id: panelId })
      setTestRes(res)
    } finally {
      setTestLoading(false)
    }
  }, [])

  const openAdd = () => {
    setError(null)
    setMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }

  const openEdit = (r: DashRecord) => {
    setError(null)
    setMode("edit")
    setForm(formFromRow(r))
    setSheetOpen(true)
  }

  const onSave = () => {
    const base = {
      xp_label: form.xp_label.trim(),
      xp_panel_url: form.xp_panel_url.trim(),
      xp_panel_username: form.xp_panel_username.trim(),
      xp_panel_api_base: form.xp_panel_api_base.trim() || "panel/api",
      xp_panel_login_secret: form.xp_panel_login_secret.trim(),
      xp_subscription_public_base: form.xp_subscription_public_base.trim(),
      xp_sort_order: form.xp_sort_order,
      xp_active: form.xp_active ? 1 : 0,
    }
    if (mode === "add") {
      void run({
        xp_action: "add",
        ...base,
        xp_panel_password: form.xp_panel_password,
      })
      return
    }
    const payload: Record<string, unknown> = {
      xp_action: "update",
      xp_id: form.xp_id,
      ...base,
    }
    if (form.xp_panel_password.trim() !== "") {
      payload.xp_panel_password = form.xp_panel_password
    }
    void run(payload)
  }

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

      {panels.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tp("empty")}</p>
      ) : (
        <div className="w-full max-w-full overflow-x-auto rounded-md border border-border">
          <table
            className={cn(
              "w-full min-w-[32rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              isFa ? "text-right" : "text-left"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">#</th>
                <th className="p-2 font-medium">{tp("colLabel")}</th>
                <th className="p-2 font-medium">{tp("colUrl")}</th>
                <th className="p-2 font-medium">{tp("colActive")}</th>
                <th className="p-2 w-10" />
              </tr>
            </thead>
            <tbody>
              {panels.map((r) => {
                const id = num(r.id)
                return (
                  <tr key={id}>
                    <td className="p-2 font-mono text-xs tabular-nums">{formatNumber(id, isFa)}</td>
                    <td className="p-2">{String(r.label ?? "")}</td>
                    <td className="max-w-[14rem] break-all p-2 text-xs">{String(r.panel_url ?? "")}</td>
                    <td className="p-2 tabular-nums">{formatNumericString(String(r.active ?? ""), isFa)}</td>
                    <td className="p-2">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                            <EllipsisVerticalIcon className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align={isFa ? "start" : "end"}>
                          <DropdownMenuItem onClick={() => void run({ xp_action: "toggle", xp_id: id })}>
                            {tp("toggle")}
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => void runPanelTest(id)}>
                            {tp("testConnection")}
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

      <p className="text-xs text-muted-foreground">{tp("passwordHint")}</p>

      <DataPagination
        meta={pagination}
        isFa={isFa}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className={cn("flex w-full flex-col sm:max-w-lg", isFa && "text-right")} side={isFa ? "left" : "right"}>
          <SheetHeader>
            <SheetTitle>{mode === "add" ? tp("sheetAdd") : tp("sheetEdit")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-4">
            <div className="space-y-2">
              <Label>{tp("fieldLabel")}</Label>
              <Input value={form.xp_label} onChange={(e) => setForm((f) => ({ ...f, xp_label: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldUrl")}</Label>
              <Input value={form.xp_panel_url} onChange={(e) => setForm((f) => ({ ...f, xp_panel_url: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldUser")}</Label>
              <Input
                value={form.xp_panel_username}
                onChange={(e) => setForm((f) => ({ ...f, xp_panel_username: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldPassword")}</Label>
              <Input
                type="password"
                autoComplete="off"
                value={form.xp_panel_password}
                onChange={(e) => setForm((f) => ({ ...f, xp_panel_password: e.target.value }))}
                placeholder={mode === "edit" ? tp("passwordKeep") : ""}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldApiBase")}</Label>
              <Input
                value={form.xp_panel_api_base}
                onChange={(e) => setForm((f) => ({ ...f, xp_panel_api_base: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldLoginSecret")}</Label>
              <Input
                type="password"
                autoComplete="off"
                value={form.xp_panel_login_secret}
                onChange={(e) => setForm((f) => ({ ...f, xp_panel_login_secret: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldSubBase")}</Label>
              <Input
                value={form.xp_subscription_public_base}
                onChange={(e) => setForm((f) => ({ ...f, xp_subscription_public_base: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldSort")}</Label>
              <Input
                type="number"
                value={form.xp_sort_order}
                onChange={(e) => setForm((f) => ({ ...f, xp_sort_order: num(e.target.value) }))}
              />
            </div>
            <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.xp_active}
                onChange={(e) => setForm((f) => ({ ...f, xp_active: e.target.checked }))}
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

      <Dialog open={testOpen} onOpenChange={setTestOpen}>
        <DialogContent className={cn("max-w-lg", isFa && "text-right")} dir={isFa ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{tp("testDialogTitle", { id: formatNumber(testPanelId, isFa) })}</DialogTitle>
            <DialogDescription>{tp("testDialogDesc")}</DialogDescription>
          </DialogHeader>
          {testLoading ? (
            <p className="text-sm text-muted-foreground">{tp("testRunning")}</p>
          ) : testRes ? (
            <div className="space-y-2 text-sm">
              <p className={testRes.ok ? "text-emerald-600 dark:text-emerald-400" : "text-destructive"}>
                {testRes.ok ? tp("testOk") : testRes.message || tp("testFail")}
              </p>
              {testRes.data != null ? (
                <pre className="max-h-48 overflow-auto rounded-md border border-border bg-muted/40 p-2 text-xs" dir="ltr">
                  {JSON.stringify(testRes.data, null, 2)}
                </pre>
              ) : null}
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setTestOpen(false)}>
              {tp("cancel")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

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
              onClick={() => deleteTarget && void run({ xp_action: "delete", xp_id: num(deleteTarget.id) })}
            >
              {tp("delete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

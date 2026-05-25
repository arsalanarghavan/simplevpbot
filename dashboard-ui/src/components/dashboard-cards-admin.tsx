"use client"

import {
  DndContext,
  PointerSensor,
  closestCenter,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core"
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { EllipsisVerticalIcon, GripVertical } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

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
import { Switch } from "@/components/ui/switch"
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
import { formatRedactedCardNumber } from "@/lib/redact-card-number"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

const METHOD_KEYS = ["c2c", "crypto", "crypto_auto"] as const
type CardsDisplayMode = "list" | "sequential" | "random"

function parseCardsDisplayMode(raw: unknown): CardsDisplayMode {
  const v = String(raw ?? "list")
  if (v === "sequential" || v === "random") return v
  return "list"
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(c: DashRecord): boolean {
  return c.active === true || c.active === 1 || c.active === "1"
}

type CardFormState = {
  edit_id: number
  card_number: string
  holder_name: string
  bank_name: string
  method_key: (typeof METHOD_KEYS)[number]
  daily_limit: number
  note: string
  active: boolean
}

function emptyForm(): CardFormState {
  return {
    edit_id: 0,
    card_number: "",
    holder_name: "",
    bank_name: "",
    method_key: "c2c",
    daily_limit: 0,
    note: "",
    active: true,
  }
}

function formFromRow(c: DashRecord): CardFormState {
  const mk = String(c.method_key ?? "c2c")
  const method_key = METHOD_KEYS.includes(mk as (typeof METHOD_KEYS)[number])
    ? (mk as (typeof METHOD_KEYS)[number])
    : "c2c"
  return {
    edit_id: num(c.id),
    card_number: String(c.card_number ?? ""),
    holder_name: String(c.holder_name ?? ""),
    bank_name: String(c.bank_name ?? ""),
    method_key,
    daily_limit: num(c.daily_limit),
    note: String(c.note ?? ""),
    active: isActiveRow(c),
  }
}

const selectClass =
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"

function SortableCardTile({
  c,
  isFa,
  canDrag,
  busy,
  saving,
  tp,
  methodLabel,
  onToggleActive,
  onEdit,
  onDelete,
}: {
  c: DashRecord
  isFa: boolean
  canDrag: boolean
  busy: boolean
  saving: boolean
  tp: (k: string) => string
  methodLabel: (raw: unknown) => string
  onToggleActive: (c: DashRecord, checked: boolean) => void
  onEdit: (c: DashRecord) => void
  onDelete: (c: DashRecord) => void
}) {
  const id = num(c.id)
  const act = isActiveRow(c)
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: !canDrag || busy || saving,
  })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div ref={setNodeRef} style={style} className={cn(isDragging && "z-10 opacity-80")}>
      <Card>
        <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
          <div className="flex min-w-0 flex-1 items-start gap-2">
            {canDrag ? (
              <button
                type="button"
                className="mt-0.5 shrink-0 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                aria-label={tp("dragHint")}
                {...attributes}
                {...listeners}
              >
                <GripVertical className="size-4" />
              </button>
            ) : null}
            <div className="min-w-0 space-y-1">
              <CardTitle className="text-base">{String(c.bank_name ?? "—")}</CardTitle>
              <CardDescription className="font-mono text-xs">
                {formatRedactedCardNumber(c.card_number)} · {String(c.holder_name ?? "")}
              </CardDescription>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <Switch
              checked={act}
              disabled={busy || saving}
              onCheckedChange={(checked) => onToggleActive(c, checked)}
              aria-label={act ? tp("badgeActive") : tp("badgeInactive")}
            />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon" className="size-8">
                  <EllipsisVerticalIcon className="size-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align={isFa ? "start" : "end"}>
                <DropdownMenuItem onClick={() => onEdit(c)}>{tp("edit")}</DropdownMenuItem>
                <DropdownMenuItem className="text-destructive" onClick={() => onDelete(c)}>
                  {tp("delete")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </CardHeader>
        <CardContent className="text-xs text-muted-foreground">
          <span>
            {tp("method")}: {methodLabel(c.method_key)}
          </span>
          {" · "}
          <span>
            {tp("dailyLimit")}: {formatNumber(num(c.daily_limit), isFa)}
          </span>
        </CardContent>
      </Card>
    </div>
  )
}

export function DashboardCardsAdmin({
  cards,
  pagination,
  settings,
  canEditDisplayMode = true,
  isFa,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  cards: DashRecord[]
  pagination: PaginationMeta | null
  settings?: DashRecord
  /** Site admin only; resellers cannot save global cards_display_mode. */
  canEditDisplayMode?: boolean
  isFa: boolean
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`cardsAdmin.${k}`)

  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<CardFormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [displayMode, setDisplayMode] = useState<CardsDisplayMode>(parseCardsDisplayMode(settings?.cards_display_mode))
  const [orderedCards, setOrderedCards] = useState<DashRecord[]>(cards)
  const [reordering, setReordering] = useState(false)
  useEffect(() => {
    setDisplayMode(parseCardsDisplayMode(settings?.cards_display_mode))
  }, [settings?.cards_display_mode])
  useEffect(() => {
    setOrderedCards(cards)
  }, [cards])

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    })
  )

  const stats = useMemo(() => {
    const slice = cards.length
    let active = 0
    for (const c of cards) {
      if (isActiveRow(c)) active += 1
    }
    return { total: pagination?.total ?? slice, active, inactive: slice - active }
  }, [cards, pagination])

  const sourceList = filter === "all" ? orderedCards : cards

  const filtered = useMemo(() => {
    if (filter === "active") return sourceList.filter(isActiveRow)
    if (filter === "inactive") return sourceList.filter((c) => !isActiveRow(c))
    return sourceList
  }, [sourceList, filter])

  const sortableIds = useMemo(
    () => (filter === "all" ? filtered.map((c) => num(c.id)).filter((id) => id > 0) : []),
    [filtered, filter]
  )

  const openAdd = useCallback(() => {
    setError(null)
    setFormMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }, [])

  const openEdit = useCallback((c: DashRecord) => {
    setError(null)
    setFormMode("edit")
    setForm(formFromRow(c))
    setSheetOpen(true)
  }, [])

  const onSaveSheet = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      if (formMode === "add") {
        const res = await postAdminMutate("card_add", {
          card_number: form.card_number,
          holder_name: form.holder_name,
          bank_name: form.bank_name,
          method_key: form.method_key,
          daily_limit: form.daily_limit,
          note: form.note,
        })
        if (!res.ok) {
          setError(res.message || tp("mutateError"))
          return
        }
      } else {
        const res = await postAdminMutate("card_update", {
          edit_id: form.edit_id,
          card_number: form.card_number,
          holder_name: form.holder_name,
          bank_name: form.bank_name,
          method_key: form.method_key,
          daily_limit: form.daily_limit,
          note: form.note,
          active: form.active ? 1 : 0,
        })
        if (!res.ok) {
          setError(res.message || tp("mutateError"))
          return
        }
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
      const res = await postAdminMutate("card_delete", { edit_id: id })
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

  const methodLabel = useCallback(
    (raw: unknown): string => {
      const key = String(raw ?? "").trim() === "mehr" ? "c2c" : String(raw ?? "")
      if (key === "crypto") return tp("method_crypto")
      if (key === "crypto_auto") return tp("method_crypto_auto")
      return tp("method_c2c")
    },
    [tp]
  )

  const onToggleActive = useCallback(
    async (c: DashRecord, checked: boolean) => {
      const f = formFromRow(c)
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("card_update", {
          edit_id: f.edit_id,
          card_number: f.card_number,
          holder_name: f.holder_name,
          bank_name: f.bank_name,
          method_key: f.method_key,
          daily_limit: f.daily_limit,
          note: f.note,
          active: checked ? 1 : 0,
        })
        if (!res.ok) {
          setError(res.message || tp("mutateError"))
          return
        }
        onMutateSuccess?.()
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, tp]
  )

  const onDragEnd = useCallback(
    async (event: DragEndEvent) => {
      if (filter !== "all") return
      const { active, over } = event
      if (!over || active.id === over.id) return
      const oldIndex = orderedCards.findIndex((c) => num(c.id) === active.id)
      const newIndex = orderedCards.findIndex((c) => num(c.id) === over.id)
      if (oldIndex < 0 || newIndex < 0) return
      const next = arrayMove(orderedCards, oldIndex, newIndex)
      setOrderedCards(next)
      setReordering(true)
      setError(null)
      try {
        const res = await postAdminMutate("card_reorder", {
          ordered_ids: next.map((row) => num(row.id)).filter((id) => id > 0),
        })
        if (!res.ok) {
          setError(res.message || tp("mutateError"))
          setOrderedCards(cards)
          return
        }
        onMutateSuccess?.()
      } finally {
        setReordering(false)
      }
    },
    [cards, filter, onMutateSuccess, orderedCards, tp]
  )

  const saveDisplayMode = useCallback(async () => {
    if (!canEditDisplayMode) return
    setSaving(true)
    setError(null)
    setSuccess(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "cards",
        cards_display_mode: displayMode,
      })
      if (!res.ok) {
        setError(res.message || tp("mutateError"))
        return
      }
      setSuccess(tp("displayModeSaved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [canEditDisplayMode, displayMode, onMutateSuccess, tp])

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
      {success ? (
        <div
          role="status"
          className="rounded-md border border-primary/30 bg-primary/10 px-3 py-2 text-sm text-foreground"
        >
          {success}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
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
      </div>
      <div
        className={cn(
          "grid gap-4 lg:items-start",
          canEditDisplayMode ? "lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]" : ""
        )}
      >
        <div className="order-2 min-w-0 space-y-4 lg:order-1">
          {pagination ? <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p> : null}

          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap items-center gap-2">
              <Label className="text-muted-foreground">{tp("filterLabel")}</Label>
              <select
                className={selectClass + " w-auto min-w-[8rem]"}
                value={filter}
                onChange={(e) => setFilter(e.target.value as "all" | "active" | "inactive")}
              >
                <option value="all">{tp("filterAll")}</option>
                <option value="active">{tp("filterActive")}</option>
                <option value="inactive">{tp("filterInactive")}</option>
              </select>
            </div>
            <Button type="button" size="sm" onClick={openAdd}>
              {tp("addCard")}
            </Button>
          </div>

          {filter === "all" && filtered.length > 1 ? (
            <p className="text-xs text-muted-foreground">{tp("dragHint")}</p>
          ) : null}

          {filtered.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("empty")}</p>
          ) : (
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={(e) => void onDragEnd(e)}>
              <SortableContext items={sortableIds} strategy={rectSortingStrategy}>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {filtered.map((c) => (
                    <SortableCardTile
                      key={num(c.id) || String(c.card_number)}
                      c={c}
                      isFa={isFa}
                      canDrag={filter === "all" && sortableIds.length > 1}
                      busy={reordering}
                      saving={saving}
                      tp={tp}
                      methodLabel={methodLabel}
                      onToggleActive={(row, checked) => void onToggleActive(row, checked)}
                      onEdit={openEdit}
                      onDelete={setDeleteTarget}
                    />
                  ))}
                </div>
              </SortableContext>
            </DndContext>
          )}

          <DataPagination
            meta={pagination}
            isFa={isFa}
            onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            perPageOptions={[40, 80, 120, 200]}
          />
        </div>

        {canEditDisplayMode ? (
          <Card className="order-1 h-fit lg:order-2 lg:sticky lg:top-4">
            <CardHeader className="space-y-1 pb-2">
              <CardTitle className="text-sm font-medium">{tp("displayModeTitle")}</CardTitle>
              <CardDescription className="text-xs leading-snug">{tp("displayModeDesc")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 pt-0">
              <div className="space-y-2">
                <Label className="text-xs text-muted-foreground">{tp("displayModeLabel")}</Label>
                <select
                  className={selectClass}
                  value={displayMode}
                  onChange={(e) => {
                    setSuccess(null)
                    setDisplayMode((e.target.value as CardsDisplayMode) || "list")
                  }}
                >
                  <option value="list">{tp("displayModeList")}</option>
                  <option value="sequential">{tp("displayModeSequential")}</option>
                  <option value="random">{tp("displayModeRandom")}</option>
                </select>
                <Button
                  type="button"
                  size="sm"
                  className="w-full"
                  disabled={saving}
                  onClick={() => void saveDisplayMode()}
                >
                  {tp("saveDisplayMode")}
                </Button>
              </div>
              <p className="text-xs leading-snug text-muted-foreground">{tp("displayModeHint")}</p>
            </CardContent>
          </Card>
        ) : null}
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className={cn("flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md", isFa && "text-right")}>
          <SheetHeader className="border-b p-4 text-left rtl:text-right">
            <SheetTitle>{formMode === "add" ? tp("addCard") : tp("editCard")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 p-4">
            <div className="space-y-2">
              <Label>{tp("cardNumber")}</Label>
              <Input
                className="font-mono"
                value={form.card_number}
                onChange={(e) => setForm((f) => ({ ...f, card_number: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("holderName")}</Label>
              <Input
                value={form.holder_name}
                onChange={(e) => setForm((f) => ({ ...f, holder_name: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("bankName")}</Label>
              <Input value={form.bank_name} onChange={(e) => setForm((f) => ({ ...f, bank_name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{tp("method")}</Label>
              <select
                className={selectClass}
                value={form.method_key}
                onChange={(e) =>
                  setForm((f) => ({
                    ...f,
                    method_key: (e.target.value as (typeof METHOD_KEYS)[number]) || "c2c",
                  }))
                }
              >
                {METHOD_KEYS.map((k) => (
                  <option key={k} value={k}>
                    {methodLabel(k)}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label>{tp("dailyLimit")}</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={form.daily_limit}
                onChange={(e) => setForm((f) => ({ ...f, daily_limit: num(e.target.value) }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("note")}</Label>
              <Input value={form.note} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))} />
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

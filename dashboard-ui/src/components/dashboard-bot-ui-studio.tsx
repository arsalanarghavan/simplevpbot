"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import {
  DndContext,
  PointerSensor,
  closestCorners,
  type DragEndEvent,
  useSensor,
  useSensors,
  useDroppable,
} from "@dnd-kit/core"
import {
  SortableContext,
  arrayMove,
  horizontalListSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { GripVertical } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

const ITEM_PREFIX = "item:"

export type UiStudioCell = {
  id: string
  enabled?: boolean
  glass?: boolean
}

export type UiSurfacePack = {
  actions?: Array<{
    id: string
    textKey?: string
    kind?: string
    glassDefault?: boolean
    templateSlot?: string
    labelFa?: string
    labelEn?: string
  }>
  labelFa?: string
  labelEn?: string
  defaultRows?: string[][]
}

function cloneRows(raw: unknown): UiStudioCell[][] {
  if (!Array.isArray(raw)) return []
  return raw.map((row) => {
    if (!Array.isArray(row)) return []
    return row.map((cell) => {
      if (!cell || typeof cell !== "object") return { id: "", enabled: true, glass: false }
      const o = cell as Record<string, unknown>
      return {
        id: String(o.id ?? ""),
        enabled: o.enabled !== false,
        glass: Boolean(o.glass),
      }
    })
  })
}

function pickLabelPreview(
  textKey: string,
  textDefaults: Record<string, unknown> | undefined,
  isFa: boolean,
): string {
  if (!textDefaults || !textKey) return textKey
  const row = textDefaults[textKey]
  if (row && typeof row === "object" && row !== null && ("fa" in row || "en" in row)) {
    const o = row as { fa?: unknown; en?: unknown }
    const s = isFa ? String(o.fa ?? "") : String(o.en ?? "")
    return s || textKey
  }
  if (typeof row === "string") {
    return row || textKey
  }
  return textKey
}

function stripItemPrefix(id: string): string {
  return id.startsWith(ITEM_PREFIX) ? id.slice(ITEM_PREFIX.length) : ""
}

function emptyDropId(surface: string, rowIndex: number): string {
  return `empty-row|${surface}|${rowIndex}`
}

function parseEmptyDropId(id: string): { surface: string; rowIndex: number } | null {
  const p = String(id).split("|")
  if (p[0] !== "empty-row" || p.length < 3) return null
  const rowIndex = parseInt(p[p.length - 1]!, 10)
  if (Number.isNaN(rowIndex)) return null
  const surface = p.slice(1, -1).join("|")
  return { surface, rowIndex }
}

function findRowIndex(rows: UiStudioCell[][], actionId: string): number {
  for (let i = 0; i < rows.length; i++) {
    if (rows[i]!.some((c) => c.id === actionId)) return i
  }
  return -1
}

function findDuplicateActionId(rows: UiStudioCell[][]): string | null {
  const seen = new Set<string>()
  for (const row of rows) {
    for (const c of row) {
      if (!c.id) continue
      if (seen.has(c.id)) return c.id
      seen.add(c.id)
    }
  }
  return null
}

function EmptyRowDropZone({
  surface,
  rowIndex,
  isFa,
  label,
}: {
  surface: string
  rowIndex: number
  isFa: boolean
  label: string
}) {
  const id = emptyDropId(surface, rowIndex)
  const { setNodeRef, isOver } = useDroppable({ id })
  return (
    <div
      ref={setNodeRef}
      className={cn(
        "flex min-h-14 items-center justify-center rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground",
        isOver && "border-primary bg-primary/10 text-foreground",
        isFa && "text-right",
      )}
    >
      {label}
    </div>
  )
}

function SortableChip({
  cell,
  disabled,
  studioTitle,
  labelPreview,
  glassPreview,
  textKeyLine,
  onToggleEnabled,
  onToggleGlass,
  enabledLabel,
  glassLabel,
  isFa,
}: {
  cell: UiStudioCell
  disabled: boolean
  studioTitle: string
  labelPreview: string
  glassPreview: string
  textKeyLine: string
  onToggleEnabled: (v: boolean) => void
  onToggleGlass: (v: boolean) => void
  enabledLabel: string
  glassLabel: string
  isFa: boolean
}) {
  const sortId = `${ITEM_PREFIX}${cell.id}`
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: sortId,
    disabled,
  })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }
  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "flex min-w-[200px] flex-col gap-2 rounded-lg border border-border bg-card p-3 text-sm shadow-sm",
        isDragging && "opacity-70",
        disabled && "opacity-50",
      )}
    >
      <div className={cn("flex items-start gap-2", isFa && "flex-row-reverse")}>
        <button
          type="button"
          className="mt-0.5 cursor-grab touch-none text-muted-foreground hover:text-foreground"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-4" />
        </button>
        <div className={cn("min-w-0 flex-1 space-y-1", isFa && "text-right")}>
          <div className="break-words font-medium leading-snug">{studioTitle}</div>
          <div className="break-all font-mono text-xs text-muted-foreground">{cell.id}</div>
          {textKeyLine ? (
            <div className="break-all font-mono text-[10px] text-muted-foreground/90">{textKeyLine}</div>
          ) : null}
          <div className="break-words text-xs leading-snug text-muted-foreground">{glassPreview || labelPreview}</div>
        </div>
      </div>
      <div className={cn("flex flex-wrap items-center gap-3", isFa && "justify-end")}>
        <div className={cn("flex items-center gap-2", isFa && "flex-row-reverse")}>
          <Switch
            id={`${sortId}-en`}
            checked={cell.enabled !== false}
            onCheckedChange={onToggleEnabled}
          />
          <Label htmlFor={`${sortId}-en`} className="text-xs">
            {enabledLabel}
          </Label>
        </div>
        <div className={cn("flex items-center gap-2", isFa && "flex-row-reverse")}>
          <Switch id={`${sortId}-gl`} checked={Boolean(cell.glass)} onCheckedChange={onToggleGlass} />
          <Label htmlFor={`${sortId}-gl`} className="text-xs">
            {glassLabel}
          </Label>
        </div>
      </div>
    </div>
  )
}

export function DashboardBotUiStudio({
  uiLayout,
  uiRegistry,
  textDefaults,
  layoutReadOnly = false,
  isFa,
  onMutateSuccess,
}: {
  uiLayout?: Record<string, unknown>
  uiRegistry?: Record<string, unknown>
  textDefaults?: Record<string, unknown>
  /** View layouts only (global Bot UI is admin-owned). */
  layoutReadOnly?: boolean
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string, o?: Record<string, string | number>) => t(`botUiStudio.${k}`, o)
  const enabledLbl = tp("enabled")
  const glassLbl = tp("glass")

  const surfacesReg = useMemo(() => {
    const reg = uiRegistry as { surfaces?: Record<string, UiSurfacePack> } | undefined
    return reg?.surfaces ?? {}
  }, [uiRegistry])

  const surfaceIds = useMemo(() => Object.keys(surfacesReg).sort(), [surfacesReg])

  const [surface, setSurface] = useState<string>("")
  const [rowsBySurface, setRowsBySurface] = useState<Record<string, UiStudioCell[][]>>({})
  const [saving, setSaving] = useState(false)
  const [resetting, setResetting] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  useEffect(() => {
    const lay = uiLayout as { surfaces?: Record<string, unknown> } | undefined
    const surf = lay?.surfaces
    if (!surf || typeof surf !== "object") {
      setRowsBySurface({})
      return
    }
    const next: Record<string, UiStudioCell[][]> = {}
    for (const k of Object.keys(surf)) {
      next[k] = cloneRows(surf[k])
    }
    setRowsBySurface(next)
  }, [uiLayout])

  useEffect(() => {
    if (!surface && surfaceIds.length > 0) {
      setSurface(surfaceIds[0]!)
    }
  }, [surface, surfaceIds])

  const pack = surface ? surfacesReg[surface] : undefined

  const metaById = useMemo(() => {
    const m = new Map<
      string,
      { textKey?: string; glassDefault?: boolean; labelFa?: string; labelEn?: string }
    >()
    for (const a of pack?.actions ?? []) {
      m.set(a.id, {
        textKey: a.textKey,
        glassDefault: a.glassDefault,
        labelFa: a.labelFa,
        labelEn: a.labelEn,
      })
    }
    return m
  }, [pack?.actions])

  const rows = rowsBySurface[surface] ?? []

  const setRows = useCallback(
    (next: UiStudioCell[][]) => {
      setRowsBySurface((prev) => ({ ...prev, [surface]: next }))
    },
    [surface],
  )

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }))

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event
      if (!over) return
      const activeAid = stripItemPrefix(String(active.id))
      if (!activeAid) return

      const overStr = String(over.id)
      const emptyDrop = parseEmptyDropId(overStr)
      if (emptyDrop && emptyDrop.surface === surface) {
        const srcRi = findRowIndex(rows, activeAid)
        if (srcRi < 0) return
        const copy = rows.map((r) => r.map((c) => ({ ...c })))
        const srcRow = copy[srcRi]!
        const sIx = srcRow.findIndex((c) => c.id === activeAid)
        if (sIx < 0) return
        const [cell] = srcRow.splice(sIx, 1)
        const tgtRi = emptyDrop.rowIndex
        if (!copy[tgtRi]) return
        copy[tgtRi]!.push(cell!)
        setRows(copy)
        return
      }

      const overAid = stripItemPrefix(overStr)
      if (!overAid) return

      const srcRi = findRowIndex(rows, activeAid)
      const dstRi = findRowIndex(rows, overAid)
      if (srcRi < 0 || dstRi < 0) return

      if (srcRi === dstRi) {
        const row = rows[srcRi]!
        const oldIndex = row.findIndex((c) => c.id === activeAid)
        const newIndex = row.findIndex((c) => c.id === overAid)
        if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) return
        const nextRow = arrayMove(row, oldIndex, newIndex)
        const copy = [...rows]
        copy[srcRi] = nextRow
        setRows(copy)
        return
      }

      const copy = rows.map((r) => r.map((c) => ({ ...c })))
      const srcRow = copy[srcRi]!
      const dstRow = copy[dstRi]!
      const sIx = srcRow.findIndex((c) => c.id === activeAid)
      const dIx = dstRow.findIndex((c) => c.id === overAid)
      if (sIx < 0 || dIx < 0) return
      const [cell] = srcRow.splice(sIx, 1)
      dstRow.splice(dIx, 0, cell!)
      setRows(copy)
    },
    [rows, setRows, surface],
  )

  const addRow = useCallback(() => {
    setRows([...rows, []])
  }, [rows, setRows])

  const deleteRow = useCallback(
    (ri: number) => {
      const row = rows[ri]
      const hasCells = row && row.length > 0
      if (hasCells || rows.length > 1) {
        if (!window.confirm(tp("confirmDeleteRow"))) return
      }
      const next = rows.filter((_, i) => i !== ri)
      setRows(next)
    },
    [rows, setRows, tp],
  )

  const onSave = useCallback(async () => {
    setSaving(true)
    setErr(null)
    setMsg(null)
    try {
      const dup = findDuplicateActionId(rows)
      if (dup) {
        setErr(tp("duplicateActions", { id: dup }))
        return
      }
      const surfacesPayload: Record<string, unknown> = {}
      surfacesPayload[surface] = rows.map((r) =>
        r.map((c) => ({
          id: c.id,
          enabled: c.enabled !== false,
          glass: Boolean(c.glass),
        })),
      )
      const res = await postAdminMutate("bot_ui_layout_save", { surfaces: surfacesPayload })
      if (!res.ok) {
        const data = res.data as { errors?: string[] } | undefined
        if (res.message === "validation_failed" && data?.errors?.length) {
          setErr(data.errors.join(" · "))
        } else {
          setErr(res.message || tp("saveError"))
        }
        return
      }
      setMsg(tp("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [onMutateSuccess, rows, surface, tp])

  const onReset = useCallback(async () => {
    setResetting(true)
    setErr(null)
    setMsg(null)
    try {
      const res = await postAdminMutate("bot_ui_layout_reset", {})
      if (!res.ok) {
        setErr(res.message || tp("resetError"))
        return
      }
      setMsg(tp("resetDone"))
      onMutateSuccess?.()
    } finally {
      setResetting(false)
    }
  }, [onMutateSuccess, tp])

  return (
    <div className={cn("mx-auto max-w-5xl space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
        {layoutReadOnly ? (
          <p className="mt-2 text-xs text-muted-foreground">{tp("readOnlyHint")}</p>
        ) : null}
        {(surface.startsWith("svc_menu_") || surface.includes("inline")) && (
          <p className="mt-2 text-xs text-muted-foreground">{tp("hintInline")}</p>
        )}
      </div>

      <div className="flex flex-wrap items-end gap-4">
        <div className="space-y-2">
          <Label htmlFor="svp-ui-surface">{tp("surface")}</Label>
          <select
            id="svp-ui-surface"
            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
            value={surface}
            onChange={(e) => setSurface(e.target.value)}
          >
            {surfaceIds.map((id) => {
              const p = surfacesReg[id]
              const lab = isFa ? (p?.labelFa ?? id) : (p?.labelEn ?? id)
              return (
                <option key={id} value={id}>
                  {lab} ({id})
                </option>
              )
            })}
          </select>
        </div>
        {!layoutReadOnly ? (
          <>
            <Button type="button" onClick={onSave} disabled={saving || !surface}>
              {saving ? tp("saving") : tp("save")}
            </Button>
            <Button type="button" variant="outline" onClick={onReset} disabled={resetting}>
              {resetting ? tp("resetting") : tp("reset")}
            </Button>
          </>
        ) : null}
      </div>

      {err && <p className="text-sm text-destructive">{err}</p>}
      {msg && !err && <p className="text-sm text-green-600 dark:text-green-400">{msg}</p>}

      {!surface ? (
        <p className="text-sm text-muted-foreground">{tp("emptySurface")}</p>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCorners}
          onDragEnd={layoutReadOnly ? () => {} : handleDragEnd}
        >
          <div className={cn("space-y-4", layoutReadOnly && "pointer-events-none opacity-90")}>
            {!layoutReadOnly ? (
              <div className={cn("flex flex-wrap gap-2", isFa && "flex-row-reverse")}>
                <Button type="button" variant="secondary" size="sm" onClick={addRow}>
                  {tp("addRow")}
                </Button>
                <span className="text-xs text-muted-foreground self-center">{tp("dragAcrossRowsHint")}</span>
              </div>
            ) : null}

            {rows.length === 0 ? (
              <p className="text-sm text-muted-foreground">{tp("noRowsHint")}</p>
            ) : null}

            <div className="space-y-6">
              {rows.map((row, ri) => {
                const sortIds = row.map((c) => `${ITEM_PREFIX}${c.id}`)
                return (
                  <div key={`row-${ri}-${row.map((c) => c.id).join(",")}`} className="space-y-2">
                    <div className={cn("flex flex-wrap items-center gap-2", isFa && "flex-row-reverse")}>
                      <span className="text-xs font-medium text-muted-foreground">
                        {tp("row", { n: ri + 1 })}
                      </span>
                      <Button type="button" variant="ghost" size="sm" className="h-7 text-destructive" onClick={() => deleteRow(ri)}>
                        {tp("deleteRow")}
                      </Button>
                    </div>
                    {row.length === 0 ? (
                      <EmptyRowDropZone
                        surface={surface}
                        rowIndex={ri}
                        isFa={isFa}
                        label={tp("dropZoneEmpty")}
                      />
                    ) : (
                      <SortableContext items={sortIds} strategy={horizontalListSortingStrategy}>
                        <div
                          className={cn(
                            "flex flex-wrap gap-3 rounded-lg border border-dashed border-border/80 bg-muted/20 p-3",
                            isFa && "flex-row-reverse",
                          )}
                        >
                          {row.map((cell) => {
                            const actionMeta = metaById.get(cell.id)
                            const tk = actionMeta?.textKey ?? ""
                            const regTitle = isFa ? actionMeta?.labelFa : actionMeta?.labelEn
                            const studioTitle =
                              regTitle && String(regTitle).trim() !== ""
                                ? String(regTitle)
                                : tk
                                  ? pickLabelPreview(tk, textDefaults, isFa)
                                  : cell.id
                            const base = tk
                              ? pickLabelPreview(tk, textDefaults, isFa)
                              : cell.id
                            const glassOn = Boolean(cell.glass) || Boolean(actionMeta?.glassDefault)
                            const glassStr = glassOn ? `⟨${base}⟩` : base
                            return (
                              <SortableChip
                                key={`${surface}-${ri}-${cell.id}`}
                                cell={cell}
                                disabled={cell.enabled === false}
                                studioTitle={studioTitle}
                                labelPreview={base}
                                glassPreview={glassStr}
                                textKeyLine={tk}
                                onToggleEnabled={(v) => {
                                  const cp = rows.map((r) => r.map((c) => ({ ...c })))
                                  const ix = cp[ri]?.findIndex((c) => c.id === cell.id)
                                  if (ix === undefined || ix < 0) return
                                  cp[ri]![ix] = { ...cell, enabled: v }
                                  setRows(cp)
                                }}
                                onToggleGlass={(v) => {
                                  const cp = rows.map((r) => r.map((c) => ({ ...c })))
                                  const ix = cp[ri]?.findIndex((c) => c.id === cell.id)
                                  if (ix === undefined || ix < 0) return
                                  cp[ri]![ix] = { ...cell, glass: v }
                                  setRows(cp)
                                }}
                                enabledLabel={enabledLbl}
                                glassLabel={glassLbl}
                                isFa={isFa}
                              />
                            )
                          })}
                        </div>
                      </SortableContext>
                    )}
                  </div>
                )
              })}
            </div>
          </div>
        </DndContext>
      )}
    </div>
  )
}

"use client"

import { useCallback, useMemo, useState, type ChangeEvent } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"
import { ChevronDown } from "lucide-react"

type TextRow = Record<string, unknown>

const MAX_LEN = 8000

/** Strip ASCII control chars (align with PHP sanitize). */
function stripControls(s: string): string {
  return s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, "")
}

export function DashboardTextsAdmin({
  texts,
  textDefaults,
  isFa,
  onMutateSuccess,
}: {
  texts: TextRow[]
  textDefaults: Record<string, string>
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`textsAdmin.${k}`)

  const byCategory = useMemo(() => {
    const m = new Map<string, TextRow[]>()
    for (const row of texts) {
      const cat = String(row.category ?? "general")
      if (!m.has(cat)) m.set(cat, [])
      m.get(cat)!.push(row)
    }
    for (const arr of m.values()) {
      arr.sort((a, b) => String(a.key_name ?? "").localeCompare(String(b.key_name ?? "")))
    }
    return Array.from(m.entries()).sort(([a], [b]) => a.localeCompare(b))
  }, [texts])

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
        <p className="mt-1 text-xs text-muted-foreground">{tp("placeholdersHint")}</p>
      </div>
      {byCategory.map(([category, rows]) => (
        <Collapsible key={category} defaultOpen className="rounded-md border border-border">
          <CollapsibleTrigger className="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm font-medium hover:bg-muted/50">
            <span>
              {tp("category")}: {category}
            </span>
            <ChevronDown className="size-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="grid gap-4 border-t border-border p-3 md:grid-cols-2">
              {rows.map((row) => (
                <TextKeyEditor
                  key={String(row.id ?? row.key_name)}
                  row={row}
                  defaultSnippet={textDefaults[String(row.key_name ?? "")] ?? ""}
                  isFa={isFa}
                  tp={tp}
                  onMutateSuccess={onMutateSuccess}
                />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      ))}
    </div>
  )
}

function TextKeyEditor({
  row,
  defaultSnippet,
  isFa,
  tp,
  onMutateSuccess,
}: {
  row: TextRow
  defaultSnippet: string
  isFa: boolean
  tp: (k: string) => string
  onMutateSuccess?: () => void
}) {
  const key = String(row.key_name ?? "")
  const initial = String(row.value ?? "")
  const [value, setValue] = useState(initial)
  const [saving, setSaving] = useState(false)
  const [resetting, setResetting] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const onSave = useCallback(async () => {
    setSaving(true)
    setErr(null)
    const trimmed = stripControls(value.slice(0, MAX_LEN))
    try {
      const res = await postAdminMutate("texts_save", { texts: { [key]: trimmed } })
      if (!res.ok) {
        setErr(res.message || tp("saveError"))
        return
      }
      setValue(trimmed)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [key, onMutateSuccess, tp, value])

  const onReset = useCallback(async () => {
    if (!defaultSnippet) return
    if (!window.confirm(tp("resetConfirm"))) return
    setResetting(true)
    setErr(null)
    try {
      const res = await postAdminMutate("text_reset_one", { text_key: key })
      if (!res.ok) {
        setErr(res.message === "unknown_key" ? tp("resetUnknownKey") : res.message || tp("resetError"))
        return
      }
      setValue(defaultSnippet)
      onMutateSuccess?.()
    } finally {
      setResetting(false)
    }
  }, [defaultSnippet, key, onMutateSuccess, tp])

  return (
    <div className="space-y-2 rounded-md border border-border/80 bg-muted/20 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label className="font-mono text-xs">{key}</Label>
        <span className="text-xs text-muted-foreground">
          {value.length}/{MAX_LEN}
        </span>
      </div>
      <Textarea
        dir="auto"
        className="min-h-[8rem] font-mono text-sm"
        value={value}
        maxLength={MAX_LEN}
        onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setValue(e.target.value)}
      />
      {defaultSnippet ? (
        <p className="text-xs text-muted-foreground">
          {tp("defaultPreview")}: <span className="line-clamp-2">{defaultSnippet}</span>
        </p>
      ) : (
        <p className="text-xs text-amber-700 dark:text-amber-400">{tp("noDefaultHint")}</p>
      )}
      {err ? (
        <div role="alert" className="rounded border border-destructive/40 bg-destructive/10 px-2 py-1 text-xs text-destructive">
          {err}
        </div>
      ) : null}
      <div className={cn("flex flex-wrap gap-2", isFa && "flex-row-reverse")}>
        <Button type="button" size="sm" disabled={saving} onClick={() => void onSave()}>
          {tp("saveOne")}
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={resetting || !defaultSnippet} onClick={() => void onReset()}>
          {tp("resetOne")}
        </Button>
      </div>
    </div>
  )
}

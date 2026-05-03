"use client"

import { useCallback, useEffect, useRef } from "react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

function selectionHtml(): string {
  const sel = window.getSelection()
  if (!sel || sel.rangeCount === 0) return ""
  const range = sel.getRangeAt(0)
  const frag = range.cloneContents()
  const div = document.createElement("div")
  div.appendChild(frag)
  return div.innerHTML
}

function insertHtml(html: string) {
  document.execCommand("insertHTML", false, html)
}

export function BroadcastRichEditor({
  value,
  onChange,
  isFa,
  disabled,
  placeholder,
}: {
  value: string
  onChange: (html: string) => void
  isFa: boolean
  disabled?: boolean
  placeholder?: string
}) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (value === "" && el.innerHTML !== "") {
      el.innerHTML = ""
      return
    }
    if (value && el.innerHTML !== value) {
      el.innerHTML = value
    }
  }, [value])

  const emit = useCallback(() => {
    const el = ref.current
    if (el) onChange(el.innerHTML)
  }, [onChange])

  const runBold = useCallback(() => {
    ref.current?.focus()
    document.execCommand("bold", false)
    emit()
  }, [emit])

  const runItalic = useCallback(() => {
    ref.current?.focus()
    document.execCommand("italic", false)
    emit()
  }, [emit])

  const runCode = useCallback(() => {
    ref.current?.focus()
    const inner = selectionHtml() || "…"
    insertHtml(`<code>${inner}</code>`)
    emit()
  }, [emit])

  const runLink = useCallback(() => {
    ref.current?.focus()
    const url = window.prompt("https://…")
    if (!url || !url.trim()) return
    const safe = url.trim().replace(/"/g, "&quot;")
    const inner = selectionHtml() || safe
    insertHtml(`<a href="${safe}">${inner}</a>`)
    emit()
  }, [emit])

  return (
    <div className="space-y-2">
      <div
        className={cn(
          "flex flex-wrap gap-1 rounded-md border border-input bg-muted/30 p-1",
          isFa && "flex-row-reverse"
        )}
      >
        <Button type="button" variant="secondary" size="sm" className="h-8 px-2 text-xs" disabled={disabled} onClick={runBold}>
          B
        </Button>
        <Button type="button" variant="secondary" size="sm" className="h-8 px-2 text-xs" disabled={disabled} onClick={runItalic}>
          I
        </Button>
        <Button type="button" variant="secondary" size="sm" className="h-8 px-2 text-xs" disabled={disabled} onClick={runCode}>
          {"</>"}
        </Button>
        <Button type="button" variant="secondary" size="sm" className="h-8 px-2 text-xs" disabled={disabled} onClick={runLink}>
          Link
        </Button>
      </div>
      <div
        ref={ref}
        className={cn(
          "min-h-[8rem] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
          isFa && "text-right"
        )}
        contentEditable={!disabled}
        dir="auto"
        suppressContentEditableWarning
        data-placeholder={placeholder || ""}
        onInput={emit}
        onBlur={emit}
      />
      <style>{`
        [contenteditable][data-placeholder]:empty:before {
          content: attr(data-placeholder);
          color: hsl(var(--muted-foreground));
          pointer-events: none;
        }
      `}</style>
    </div>
  )
}

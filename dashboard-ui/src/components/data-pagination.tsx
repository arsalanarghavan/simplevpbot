"use client"

import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

export function DataPagination({
  meta,
  isFa,
  onPageChange,
  onPerPageChange,
  perPageOptions = [10, 20, 40, 50, 100],
  className,
}: {
  meta: PaginationMeta | null | undefined
  isFa: boolean
  onPageChange: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  perPageOptions?: number[]
  className?: string
}) {
  const { t } = useTranslation()
  if (!meta || meta.total <= 0) return null
  const { page, perPage, total } = meta
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const from = total === 0 ? 0 : (page - 1) * perPage + 1
  const to = Math.min(total, page * perPage)
  const canPrev = page > 1
  const canNext = page < totalPages

  return (
    <div
      className={cn(
        "flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-sm",
        isFa && "flex-row-reverse",
        className
      )}
    >
      <p className={cn("text-muted-foreground", isFa && "text-right")}>
        {t("pagination.range", {
          from: formatNumber(from, isFa),
          to: formatNumber(to, isFa),
          total: formatNumber(total, isFa),
        })}
      </p>
      <div className={cn("flex flex-wrap items-center gap-2", isFa && "flex-row-reverse")}>
        {onPerPageChange ? (
          <label className={cn("flex items-center gap-1 text-xs text-muted-foreground", isFa && "flex-row-reverse")}>
            <span>{t("pagination.perPage")}</span>
            <select
              className="h-8 rounded-md border border-input bg-background px-2 text-xs shadow-xs outline-none"
              value={perPage}
              onChange={(e) => onPerPageChange(Number(e.target.value))}
            >
              {perPageOptions.map((n) => (
                <option key={n} value={n}>
                  {formatNumber(n, isFa)}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <div className={cn("flex items-center gap-1", isFa && "flex-row-reverse")}>
          <Button type="button" variant="outline" size="sm" disabled={!canPrev} onClick={() => onPageChange(page - 1)}>
            {t("pagination.prev")}
          </Button>
          <span className="min-w-[5rem] text-center text-xs tabular-nums text-muted-foreground">
            {formatNumber(page, isFa)} / {formatNumber(totalPages, isFa)}
          </span>
          <Button type="button" variant="outline" size="sm" disabled={!canNext} onClick={() => onPageChange(page + 1)}>
            {t("pagination.next")}
          </Button>
        </div>
      </div>
    </div>
  )
}

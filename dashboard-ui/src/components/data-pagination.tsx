"use client"

import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { dashActionsClass, dashDir } from "@/lib/dash-locale"
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
      dir={dashDir(isFa)}
      className={cn(
        "flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3 text-sm",
        className
      )}
    >
      <p className="text-muted-foreground">
        {t("pagination.range", {
          from: formatNumber(from, isFa),
          to: formatNumber(to, isFa),
          total: formatNumber(total, isFa),
        })}
      </p>
      <div className={dashActionsClass()}>
        {onPerPageChange ? (
          <label className="flex items-center gap-1 text-xs text-muted-foreground">
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
        <div className="flex items-center gap-1">
          <Button type="button" variant="outline" size="sm" disabled={!canPrev} onClick={() => onPageChange(page - 1)}>
            {t("pagination.prev")}
          </Button>
          <span className="min-w-[5rem] text-center text-xs tabular-nums text-muted-foreground" dir="ltr">
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

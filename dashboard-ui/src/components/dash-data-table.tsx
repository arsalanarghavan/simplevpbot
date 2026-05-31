"use client"

import type { ReactNode } from "react"

import { dashDir } from "@/lib/dash-locale"
import { cn } from "@/lib/utils"

const cellClass = "p-2 text-start align-top"

export function DashTableShell({
  isFa,
  minWidth,
  colWidths,
  children,
  className,
}: {
  isFa: boolean
  /** e.g. "42rem" */
  minWidth?: string
  /** Percent widths in column order, e.g. ["7%", "20%", ...] */
  colWidths: string[]
  children: ReactNode
  className?: string
}) {
  return (
    <div className={cn("w-full max-w-full overflow-x-auto rounded-md border border-border", className)}>
      <table
        dir={dashDir(isFa)}
        className={cn(
          "w-full table-fixed border-collapse text-sm text-start",
          "[&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border"
        )}
        style={minWidth ? { minWidth } : undefined}
      >
        <colgroup>
          {colWidths.map((w, i) => (
            <col key={i} style={{ width: w }} />
          ))}
        </colgroup>
        {children}
      </table>
    </div>
  )
}

export function DashTh({
  children,
  className,
}: {
  children?: ReactNode
  className?: string
}) {
  return <th className={cn(cellClass, "font-medium", className)}>{children}</th>
}

export function DashTd({
  children,
  className,
  dir,
  colSpan,
}: {
  children?: ReactNode
  className?: string
  dir?: "ltr" | "rtl"
  colSpan?: number
}) {
  return (
    <td dir={dir} colSpan={colSpan} className={cn(cellClass, className)}>
      {children}
    </td>
  )
}

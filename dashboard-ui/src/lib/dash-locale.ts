import { cn } from "@/lib/utils"

/** Document direction for dashboard content regions. */
export function dashDir(isFa: boolean): "rtl" | "ltr" {
  return isFa ? "rtl" : "ltr"
}

/** Page root spacing + text alignment (pair with dir={dashDir(isFa)}). */
export function dashPageRootClass(isFa: boolean, extra?: string): string {
  return cn("space-y-6", isFa ? "text-right" : "text-left", extra)
}

/** @deprecated Use dashPageRootClass + dir={dashDir(isFa)} */
export function dashContentClass(isFa: boolean, extra?: string): string {
  return dashPageRootClass(isFa, extra)
}

/** Page header row: title block start, actions end (respects dir; no flex-row-reverse). */
export function dashPageHeaderClass(extra?: string): string {
  return cn("flex flex-wrap items-start justify-between gap-3", extra)
}

/** Action button cluster in page headers / toolbars. */
export function dashActionsClass(extra?: string): string {
  return cn("flex shrink-0 flex-wrap items-center gap-2", extra)
}

/** Table / form text alignment that follows dir. */
export function dashTextAlign(isFa: boolean): string {
  return isFa ? "text-right" : "text-left"
}

/**
 * Inline flex row for icon+label groups. Does NOT reverse — rely on parent dir.
 * @deprecated Prefer dashActionsClass; kept for gradual migration.
 */
export function dashFlexRowClass(_isFa: boolean, extra?: string): string {
  return cn("flex flex-wrap items-center gap-2", extra)
}

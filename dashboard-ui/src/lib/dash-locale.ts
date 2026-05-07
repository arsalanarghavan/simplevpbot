import { cn } from "@/lib/utils"

/** Content region: Persian RTL + digits; English LTR. */
export function dashContentClass(isFa: boolean, extra?: string): string {
  return cn(isFa ? "text-right" : "text-left", extra)
}

/** Flex row that follows reading direction (actions align to start in each locale). */
export function dashFlexRowClass(isFa: boolean, extra?: string): string {
  return cn("flex flex-wrap items-center gap-2", isFa ? "flex-row-reverse" : "flex-row", extra)
}

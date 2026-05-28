/** Legacy `wholesale_lines` tab redirects to plans (wholesale feature removed). */
export function resolveLegacyPlansTab(tab: string): { tab: string } {
  if (tab === "wholesale_lines") return { tab: "plans" }
  return { tab }
}

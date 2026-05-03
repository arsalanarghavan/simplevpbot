export type DashLocation = {
  tab: string
  userDetailId: number | null
}

/**
 * Parse `/dashboard/...` path: list tab, optional user detail `/dashboard/users/u/{id}/`.
 */
export function parseDashFromPath(pathname: string): DashLocation {
  const path = (pathname || "").replace(/\/+$/, "") || "/"
  const userM = path.match(/\/dashboard\/users\/u\/(\d+)(?:\/|$)/)
  if (userM) {
    const id = Number(userM[1])
    return { tab: "users", userDetailId: Number.isFinite(id) && id > 0 ? id : null }
  }
  const sub = path.match(/\/dashboard\/([^/]+)$/)
  if (sub) {
    let tab = sub[1]
    if (tab === "inbound_link") {
      tab = "xui_panels"
    }
    if (tab === "general") {
      tab = "monitoring"
    }
    return { tab, userDetailId: null }
  }
  if (/\/dashboard$/.test(path)) return { tab: "dashboard", userDetailId: null }
  return { tab: "dashboard", userDetailId: null }
}

/** Parse the first /dashboard/{tab}/ segment. Bare /dashboard/ → dashboard (SPA home). */
export function parseActiveDashTab(boot: { dashPath?: string } | undefined | null): string {
  if (typeof window !== "undefined") {
    return parseDashFromPath(window.location.pathname).tab
  }
  if (boot?.dashPath) {
    const s = String(boot.dashPath).trim()
    const parts = s.split("/").filter(Boolean)
    if (parts[0] === "users" && parts[1] === "u" && parts[2] && /^\d+$/.test(parts[2])) {
      return "users"
    }
    const k = parts[0]
    if (k) {
      if (k === "inbound_link") return "xui_panels"
      if (k === "general") return "monitoring"
      return k
    }
  }
  return "dashboard"
}

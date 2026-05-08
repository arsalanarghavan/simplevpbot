export type DashLocation = {
  tab: string
  userDetailId: number | null
  resellerContextId?: number | null
}

/**
 * Parse `/dashboard/...` path: list tab, optional user detail `/dashboard/users/u/{id}/`.
 */
export function parseDashFromPath(pathname: string): DashLocation {
  const path = (pathname || "").replace(/\/+$/, "") || "/"
  if (/\/dashboard\/login(?:\/|$)/.test(path)) {
    return { tab: "login", userDetailId: null }
  }
  const userM = path.match(/\/dashboard\/users\/u\/(\d+)(?:\/|$)/)
  if (userM) {
    const id = Number(userM[1])
    return { tab: "users", userDetailId: Number.isFinite(id) && id > 0 ? id : null, resellerContextId: null }
  }
  const resellerM = path.match(/\/dashboard\/reseller_workspace\/(\d+)(?:\/|$)/)
  if (resellerM) {
    const id = Number(resellerM[1])
    return { tab: "reseller_workspace", userDetailId: null, resellerContextId: Number.isFinite(id) && id > 0 ? id : null }
  }
  const sub = path.match(/\/dashboard\/([^/]+)$/)
  if (sub) {
    let tab = sub[1]
    if (tab === "inbound_link") {
      tab = "xui_panels"
    }
    if (tab === "panel_inbounds") {
      tab = "configs"
    }
    if (tab === "general") {
      tab = "monitoring"
    }
    return { tab, userDetailId: null, resellerContextId: null }
  }
  if (/\/dashboard$/.test(path)) return { tab: "dashboard", userDetailId: null, resellerContextId: null }
  return { tab: "dashboard", userDetailId: null, resellerContextId: null }
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
      if (k === "login") return "login"
      if (k === "inbound_link") return "xui_panels"
      if (k === "panel_inbounds") return "configs"
      if (k === "general") return "monitoring"
      return k
    }
  }
  return "dashboard"
}

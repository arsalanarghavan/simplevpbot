/** Laravel API base URL for dashboard SPA. */
export function apiBase(boot?: Record<string, unknown>): string {
  const fromEnv = import.meta.env.VITE_API_URL
  if (typeof fromEnv === "string" && fromEnv.trim() !== "") {
    return fromEnv.replace(/\/$/, "")
  }
  const b = boot ?? window.__SIMPLEVPBOT_DASH__ ?? {}
  return String((b as { restUrl?: string }).restUrl || "/api/v1").replace(/\/$/, "")
}

/** Map legacy WP REST paths (`/dashboard/admin/...`) to Laravel `/admin/...`. */
export function normalizeAdminApiPath(path: string): string {
  const p = path.startsWith("/") ? path : `/${path}`
  if (p.startsWith("/dashboard/admin/")) {
    return p.replace("/dashboard/admin/", "/admin/")
  }
  return p
}

export function apiHeaders(boot?: Record<string, unknown>): HeadersInit {
  const b = boot ?? window.__SIMPLEVPBOT_DASH__ ?? {}
  const nonce = String((b as { nonce?: string }).nonce || "")
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  }
  if (nonce) {
    headers["X-WP-Nonce"] = nonce
  }
  return headers
}

/** Prefer human-readable provision / API errors from a mutate response. */
export function adminMutateErrorText(
  res: AdminMutateResult,
  fallback: string
): string {
  if (res.message && String(res.message).trim()) {
    return String(res.message)
  }
  if (res.reason && String(res.reason).trim()) {
    return String(res.reason)
  }
  const d = res.data
  if (d && typeof d === "object") {
    const rec = d as Record<string, unknown>
    const pe = rec.provision_error
    if (typeof pe === "string" && pe.trim()) {
      return pe
    }
    const msg = rec.message
    if (typeof msg === "string" && msg.trim()) {
      return msg
    }
    const rsn = rec.reason
    if (typeof rsn === "string" && rsn.trim()) {
      return rsn
    }
  }
  return fallback
}

/** Parse REST body as JSON; on failure return a short diagnostic (status + body snippet). */
async function parseAdminRestJson(res: Response): Promise<Record<string, unknown>> {
  const text = await res.text()
  if (!text.trim()) {
    if (!res.ok) {
      return { ok: false, message: `http_${res.status}` }
    }
    return {}
  }
  try {
    const json = JSON.parse(text) as Record<string, unknown>
    if (!res.ok && typeof json.message !== "string" && typeof json.reason !== "string") {
      json.message = `http_${res.status}`
    }
    return json
  } catch {
    const snippet = text.replace(/\s+/g, " ").trim().slice(0, 120)
    return {
      ok: false,
      message: snippet ? `bad_json (${res.status}: ${snippet})` : `bad_json (${res.status})`,
    }
  }
}

export type AdminMutateResult = {
  ok: boolean
  code?: string
  message?: string
  plan_id?: number
  data?: unknown
  reason?: string
  iterations?: number
  transaction_id?: number
  notify_sent?: boolean
  rows?: unknown[]
  /** Unknown catalog panel IDs skipped during reseller_panel_prices_save (admin path). */
  skipped_panel_ids?: number[]
  users?: unknown[]
  billing?: Record<string, unknown>
  invited?: Record<string, unknown>
}

export async function postAdminMutate(
  op: string,
  params: Record<string, unknown>
): Promise<AdminMutateResult> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const nonce = String((boot as { nonce?: string }).nonce || "")
  const path = typeof window !== "undefined" ? window.location.pathname : ""
  const m = path.match(/\/dashboard\/reseller_workspace\/(\d+)(?:\/|$)/)
  const resellerCtx = m ? Number(m[1]) : 0
  const payload: Record<string, unknown> = { op, ...params }
  if (resellerCtx > 0 && !("reseller_context_svp_user_id" in payload)) {
    payload.reseller_context_svp_user_id = resellerCtx
  }
  const res = await fetch(`${restBase}/dashboard/admin/mutate`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": nonce,
    },
    credentials: "include",
    body: JSON.stringify(payload),
  })
  const json = await parseAdminRestJson(res)
  const skippedRaw = json.skipped_panel_ids
  const skipped_panel_ids = Array.isArray(skippedRaw)
    ? skippedRaw.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x > 0)
    : undefined

  return {
    ok: Boolean(json.ok),
    code: typeof json.code === "string" ? json.code : undefined,
    message: typeof json.message === "string" ? json.message : undefined,
    plan_id: typeof json.plan_id === "number" ? json.plan_id : undefined,
    data: "data" in json ? json.data : undefined,
    reason: typeof json.reason === "string" ? json.reason : undefined,
    iterations: typeof json.iterations === "number" ? json.iterations : undefined,
    transaction_id: typeof json.transaction_id === "number" ? json.transaction_id : undefined,
    notify_sent: typeof json.notify_sent === "boolean" ? json.notify_sent : undefined,
    rows: Array.isArray(json.rows) ? json.rows : undefined,
    skipped_panel_ids: skipped_panel_ids?.length ? skipped_panel_ids : undefined,
    users: Array.isArray(json.users) ? json.users : undefined,
  }
}

/**
 * GET helper for dashboard admin REST (nonce + credentials).
 */
export async function getAdminJson(path: string, query: Record<string, string | number>): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const nonce = String((boot as { nonce?: string }).nonce || "")
  const q = new URLSearchParams()
  for (const [k, v] of Object.entries(query)) {
    q.set(k, String(v))
  }
  const p = path.startsWith("/") ? path : `/${path}`
  const url = `${restBase}${p}?${q.toString()}`
  const res = await fetch(url, {
    method: "GET",
    headers: {
      "X-WP-Nonce": nonce,
    },
    credentials: "include",
  })
  return parseAdminRestJson(res)
}

/**
 * POST helper for dashboard admin REST (nonce + credentials).
 */
export async function postAdminJson(
  path: string,
  body: Record<string, unknown>
): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const nonce = String((boot as { nonce?: string }).nonce || "")
  const p = path.startsWith("/") ? path : `/${path}`
  const res = await fetch(`${restBase}${p}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": nonce,
    },
    credentials: "include",
    body: JSON.stringify(body),
  })
  return parseAdminRestJson(res)
}

/**
 * POST multipart/form-data for dashboard admin REST (file uploads).
 */
export async function postAdminFormData(
  path: string,
  formData: FormData
): Promise<Record<string, unknown>> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const nonce = String((boot as { nonce?: string }).nonce || "")
  const p = path.startsWith("/") ? path : `/${path}`
  const res = await fetch(`${restBase}${p}`, {
    method: "POST",
    headers: {
      "X-WP-Nonce": nonce,
    },
    credentials: "include",
    body: formData,
  })
  return parseAdminRestJson(res)
}

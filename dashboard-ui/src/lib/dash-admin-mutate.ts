export type AdminMutateResult = {
  ok: boolean
  code?: string
  message?: string
  plan_id?: number
  data?: unknown
  reason?: string
  iterations?: number
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
  let json: Record<string, unknown> = {}
  try {
    json = (await res.json()) as Record<string, unknown>
  } catch {
    return { ok: false, message: "bad_json" }
  }
  return {
    ok: Boolean(json.ok),
    code: typeof json.code === "string" ? json.code : undefined,
    message: typeof json.message === "string" ? json.message : undefined,
    plan_id: typeof json.plan_id === "number" ? json.plan_id : undefined,
    data: "data" in json ? json.data : undefined,
    reason: typeof json.reason === "string" ? json.reason : undefined,
    iterations: typeof json.iterations === "number" ? json.iterations : undefined,
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
  try {
    return (await res.json()) as Record<string, unknown>
  } catch {
    return { ok: false, message: "bad_json" }
  }
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
  try {
    return (await res.json()) as Record<string, unknown>
  } catch {
    return { ok: false, message: "bad_json" }
  }
}

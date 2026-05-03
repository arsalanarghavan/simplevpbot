export type MediaUploadResult = { ok: true; url: string } | { ok: false; message: string }

export async function postDashboardMediaUpload(file: File): Promise<MediaUploadResult> {
  const boot = window.__SIMPLEVPBOT_DASH__ || {}
  const restBase = String((boot as { restUrl?: string }).restUrl || "").replace(/\/$/, "")
  if (!restBase) {
    return { ok: false, message: "no_rest" }
  }
  const nonce = String((boot as { nonce?: string }).nonce || "")
  const fd = new FormData()
  fd.append("file", file)
  const res = await fetch(`${restBase}/dashboard/admin/media`, {
    method: "POST",
    headers: {
      "X-WP-Nonce": nonce,
    },
    credentials: "include",
    body: fd,
  })
  let json: Record<string, unknown> = {}
  try {
    json = (await res.json()) as Record<string, unknown>
  } catch {
    return { ok: false, message: "bad_json" }
  }
  if (!json.ok) {
    return { ok: false, message: typeof json.message === "string" ? json.message : "upload_failed" }
  }
  const url = typeof json.url === "string" ? json.url : ""
  if (!url) return { ok: false, message: "no_url" }
  return { ok: true, url }
}

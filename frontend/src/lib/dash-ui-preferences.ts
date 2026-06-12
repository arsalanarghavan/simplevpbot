export type UiLang = "fa" | "en"
export type UiTheme = "light" | "dark" | "system"
export type UiSidebar = "expanded" | "collapsed"

export type UiPreferencesPatch = {
  ui_accent?: string
  ui_lang?: UiLang
  ui_theme?: UiTheme
  ui_sidebar?: UiSidebar
}

export function saveUiPreferences(
  patch: UiPreferencesPatch,
  opts: { restUrl: string; nonce: string }
): Promise<void> {
  const base = String(opts.restUrl || "").replace(/\/$/, "")
  if (!base || !opts.nonce) return Promise.resolve()
  return fetch(`${base}/dashboard/ui-preferences`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": opts.nonce,
    },
    credentials: "include",
    body: JSON.stringify(patch),
  }).then(() => {})
}

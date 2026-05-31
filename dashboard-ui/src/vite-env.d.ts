/// <reference types="vite/client" />

interface Window {
  __SIMPLEVPBOT_DASH__?: {
    restUrl?: string
    nonce?: string
    lang?: "fa" | "en"
    locale?: string
    isRtl?: boolean
    /** When false, SPA shows login only (no wp_rest nonce). */
    isLoggedIn?: boolean
    loginNonce?: string
    isAdmin?: boolean
    isReseller?: boolean
    activePersona?: "admin" | "reseller" | "user"
    availablePersonas?: Array<"admin" | "reseller" | "user">
    /** Reseller permission map from PHP (same keys as server-side). */
    actorPermissions?: Record<string, boolean>
    svpUserId?: number
    /** Actor bot-user row summary for sidebar footer (label + messenger IDs). */
    user?: {
      label?: string
      tg_user_id?: number
      bale_user_id?: number
    }
    /** True when a site admin is viewing the dashboard as a reseller (signed cookie). */
    impersonating?: boolean
    impersonationTargetId?: number
    impersonationTargetLabel?: string
    logoutUrl?: string
    dashboardUrl?: string
    dashboardLoginUrl?: string
    /** First segment of /dashboard/{segment}/ (from PHP) */
    dashPath?: string
    siteName?: string
    siteIconUrl?: string
    branding?: {
      scope?: string
      siteName?: string
      logoUrl?: string
      faviconUrl?: string
      themePrimary?: string
      themeAccent?: string
      customDomain?: string
      cssVariables?: Record<string, string>
    }
    /** IANA timezone from site (e.g. Asia/Tehran) for consistent date display */
    siteTimeZone?: string
    /** User accent preset (default, red, blue, …) */
    uiAccent?: string
  }
}

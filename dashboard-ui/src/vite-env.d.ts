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
    svpUserId?: number
    logoutUrl?: string
    dashboardUrl?: string
    dashboardLoginUrl?: string
    /** First segment of /dashboard/{segment}/ (from PHP) */
    dashPath?: string
    siteName?: string
    siteIconUrl?: string
    /** IANA timezone from site (e.g. Asia/Tehran) for consistent date display */
    siteTimeZone?: string
  }
}

/// <reference types="vite/client" />

interface Window {
  __SIMPLEVPBOT_DASH__?: {
    restUrl?: string
    nonce?: string
    lang?: "fa" | "en"
    locale?: string
    isAdmin?: boolean
    svpUserId?: number
    logoutUrl?: string
    dashboardUrl?: string
    /** First segment of /dashboard/{segment}/ (from PHP) */
    dashPath?: string
    siteName?: string
    siteIconUrl?: string
    /** IANA timezone from site (e.g. Asia/Tehran) for consistent date display */
    siteTimeZone?: string
  }
}

/** Bot settings keys grouped per platform (extend array for new messengers). */
export type BotPlatformId = "telegram" | "bale"

export type BotPlatformConfig = {
  id: BotPlatformId
  titleKey: string
  summaryUsernameKey: string
  fieldKeys: readonly (keyof BotPlatformForm)[]
}

export type BotPlatformForm = {
  telegram_token: string
  bale_token: string
  telegram_webhook_secret: string
  bale_webhook_secret: string
  telegram_secret_header: string
  bale_wallet_provider_token: string
}

export const BOT_PLATFORMS: readonly BotPlatformConfig[] = [
  {
    id: "telegram",
    titleKey: "botsAdmin.platformTelegram",
    summaryUsernameKey: "botsAdmin.tgUser",
    fieldKeys: ["telegram_token", "telegram_webhook_secret", "telegram_secret_header"],
  },
  {
    id: "bale",
    titleKey: "botsAdmin.platformBale",
    summaryUsernameKey: "botsAdmin.baleUser",
    fieldKeys: ["bale_token", "bale_webhook_secret", "bale_wallet_provider_token"],
  },
] as const

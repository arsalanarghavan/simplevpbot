"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"

import { SiteSettingsLogsTab } from "@/components/site-settings/site-settings-logs-tab"
import { SiteSettingsNotificationsTab } from "@/components/site-settings/site-settings-notifications-tab"
import { SiteSettingsProxyTab } from "@/components/site-settings/site-settings-proxy-tab"
import { SiteSettingsResellersTab } from "@/components/site-settings/site-settings-resellers-tab"
import { SiteSettingsWhitelabelTab } from "@/components/site-settings/site-settings-whitelabel-tab"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  readSiteSubtabFromUrl,
  writeSiteSubtabToUrl,
  type SiteSettingsSubtab,
} from "@/lib/site-settings-subtab"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>
type WpPage = { id: number; title: string }

export function DashboardSiteSettingsAdmin({
  settings,
  wpPages,
  plans,
  resellers,
  resellerPermissionsMap,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  wpPages: WpPage[]
  plans: DashRecord[]
  resellers: DashRecord[]
  resellerPermissionsMap: Record<string, Record<string, boolean>>
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`siteSettings.${k}`)
  const [subtab, setSubtab] = useState<SiteSettingsSubtab>(() => readSiteSubtabFromUrl())

  useEffect(() => {
    setSubtab(readSiteSubtabFromUrl())
  }, [])

  useEffect(() => {
    if (typeof window === "undefined") return
    if (window.location.hash !== "#whitelabel-support") return
    setSubtab("whitelabel")
    writeSiteSubtabToUrl("whitelabel")
  }, [])

  const onSubtabChange = useCallback((v: string) => {
    const next = v as SiteSettingsSubtab
    setSubtab(next)
    writeSiteSubtabToUrl(next)
  }, [])

  const pages = wpPages ?? []
  const resellerMap = resellerPermissionsMap ?? {}

  return (
    <div className={cn("space-y-4", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      <Tabs value={subtab} onValueChange={onSubtabChange} className="w-full">
        <TabsList className={cn("flex h-auto w-full flex-wrap", isFa && "flex-row-reverse")}>
          <TabsTrigger value="whitelabel">{tp("tabWhitelabel")}</TabsTrigger>
          <TabsTrigger value="proxy">{tp("tabProxy")}</TabsTrigger>
          <TabsTrigger value="notifications">{tp("tabNotifications")}</TabsTrigger>
          <TabsTrigger value="logs">{tp("tabLogs")}</TabsTrigger>
          <TabsTrigger value="resellers">{tp("tabResellers")}</TabsTrigger>
        </TabsList>

        <TabsContent value="whitelabel" className="mt-4">
          <SiteSettingsWhitelabelTab
            settings={settings}
            wpPages={pages}
            plans={plans}
            isFa={isFa}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="proxy" className="mt-4">
          <SiteSettingsProxyTab settings={settings} isFa={isFa} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        <TabsContent value="notifications" className="mt-4">
          <SiteSettingsNotificationsTab settings={settings} isFa={isFa} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        <TabsContent value="logs" className="mt-4">
          <SiteSettingsLogsTab isFa={isFa} />
        </TabsContent>
        <TabsContent value="resellers" className="mt-4">
          <SiteSettingsResellersTab
            settings={settings}
            resellers={resellers}
            resellerPermissionsMap={resellerMap}
            isFa={isFa}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
      </Tabs>
    </div>
  )
}

"use client"

import { useTranslation } from "react-i18next"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"

export function DashboardResellerReportsPlaceholder({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`resellerReportsPlaceholder.${k}`)

  return (
    <div className={dashPageRootClass(isFa, "mx-auto w-full max-w-3xl")} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("comingSoon")}</CardTitle>
          <CardDescription>{tp("subtitle")}</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
        </CardContent>
      </Card>
    </div>
  )
}

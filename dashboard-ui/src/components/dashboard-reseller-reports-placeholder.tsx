"use client"

import { useTranslation } from "react-i18next"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { cn } from "@/lib/utils"

export function DashboardResellerReportsPlaceholder({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`resellerReportsPlaceholder.${k}`)

  return (
    <div className={cn("mx-auto w-full max-w-3xl space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>
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

"use client"

import { useTranslation } from "react-i18next"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"

export function DashboardLogsAdmin({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`logsAdmin.${k}`)

  return (
    <div className={dashPageRootClass(isFa, "space-y-4")} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("whereTitle")}</CardTitle>
          <CardDescription>{tp("whereDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          <ul className={cn("list-disc space-y-2 ps-5", isFa && "list-none space-y-2 pe-5 ps-0 [direction:rtl]")}>
            <li>{tp("hintPhp")}</li>
            <li>{tp("hintWebserver")}</li>
            <li>{tp("hintHost")}</li>
          </ul>
          <p className="text-muted-foreground">{tp("noTail")}</p>
        </CardContent>
      </Card>
    </div>
  )
}

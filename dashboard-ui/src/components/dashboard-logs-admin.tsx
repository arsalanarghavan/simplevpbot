"use client"

import { useTranslation } from "react-i18next"

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { cn } from "@/lib/utils"

export function DashboardLogsAdmin({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`logsAdmin.${k}`)

  return (
    <div className={cn("space-y-4", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>
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

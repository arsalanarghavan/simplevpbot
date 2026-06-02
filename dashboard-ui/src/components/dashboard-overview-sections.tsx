"use client"

import type { ReactNode } from "react"
import { useTranslation } from "react-i18next"
import { formatNumber } from "@/lib/format-locale"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { dashDir } from "@/lib/dash-locale"
import { overviewAccentOutlineBtn } from "@/lib/chart-accent"
import {
  formatOverviewAmount,
  formatOverviewDate,
  overviewNum,
  receiptAmount,
  receiptStatusBadgeVariant,
  userDisplayLabel,
  userStatusBadgeVariant,
  type DashRecord,
} from "@/lib/overview-rows"
import { cn } from "@/lib/utils"

export function OverviewSectionCard({
  title,
  description,
  viewAllLabel,
  onViewAll,
  isFa,
  children,
  className,
}: {
  title: string
  description?: string
  viewAllLabel: string
  onViewAll: () => void
  isFa: boolean
  children: ReactNode
  className?: string
}) {
  return (
    <Card className={cn("overflow-hidden border-border/80 shadow-sm", className)}>
      <CardHeader className="border-b border-border/60 bg-muted/20 pb-3">
        <div
          className={cn(
            "flex flex-wrap items-start justify-between gap-2",
            isFa && "flex-row-reverse"
          )}
        >
          <div className={cn("min-w-0 space-y-0.5", isFa && "text-start")}>
            <CardTitle className="text-base">{title}</CardTitle>
            {description ? (
              <CardDescription className="text-pretty">{description}</CardDescription>
            ) : null}
          </div>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className={cn("shrink-0", overviewAccentOutlineBtn)}
            onClick={onViewAll}
          >
            {viewAllLabel}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="p-0">{children}</CardContent>
    </Card>
  )
}

function OverviewEmpty({ message, isFa }: { message: string; isFa: boolean }) {
  return (
    <p className={cn("px-4 py-8 text-sm text-muted-foreground", isFa ? "text-right" : "text-center")}>
      {message}
    </p>
  )
}

function OverviewDataTable({
  isFa,
  headers,
  rows,
}: {
  isFa: boolean
  headers: string[]
  rows: ReactNode[]
}) {
  if (rows.length === 0) return null
  return (
    <Table dir={dashDir(isFa)}>
      <TableHeader>
        <TableRow className="hover:bg-transparent">
          {headers.map((h) => (
            <TableHead key={h} className={cn(isFa && "text-start")}>
              {h}
            </TableHead>
          ))}
        </TableRow>
      </TableHeader>
      <TableBody>{rows}</TableBody>
    </Table>
  )
}

function ClickableRow({
  onClick,
  children,
}: {
  onClick: () => void
  children: ReactNode
}) {
  return (
    <TableRow
      className="cursor-pointer hover:bg-primary/5"
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault()
          onClick()
        }
      }}
      tabIndex={0}
      role="button"
    >
      {children}
    </TableRow>
  )
}

export function OverviewRecentUsers({
  rows,
  isFa,
  onViewAll,
  onOpenUser,
}: {
  rows: DashRecord[]
  isFa: boolean
  onViewAll: () => void
  onOpenUser: (id: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const statusLabel = (st: string) =>
    t(`usersAdmin.status_${st}`, { defaultValue: st || "—" })

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenUser(id)}>
        <TableCell className={cn("font-medium", isFa && "text-start")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell className={cn(isFa && "text-start")}>
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {statusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs", isFa && "text-start")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentUsers")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
      isFa={isFa}
    >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")} isFa={isFa} />
      ) : (
        <OverviewDataTable
          isFa={isFa}
          headers={[tp("colUser"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentReceipts({
  rows,
  isFa,
  onViewAll,
  onOpenReceipt,
}: {
  rows: DashRecord[]
  isFa: boolean
  onViewAll: () => void
  onOpenReceipt: (row: DashRecord) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const rt = (k: string) => t(`receiptsAdmin.${k}`, { defaultValue: k })

  const receiptStatusLabel = (st: string) => {
    if (st === "pending") return rt("statusPending")
    if (st === "processing") return rt("statusProcessing")
    if (st === "approved") return rt("statusApproved")
    if (st === "rejected") return rt("statusRejected")
    return st || "—"
  }

  const tableRows = rows.map((r) => {
    const id = overviewNum(r.id)
    const st = String(r.status ?? "").toLowerCase()
    const label = String(r.user_label ?? r.user_name ?? "").trim() || userDisplayLabel(r)
    const amt = receiptAmount(r)
    return (
      <ClickableRow key={id} onClick={() => onOpenReceipt(r)}>
        <TableCell className={cn("max-w-[10rem] truncate font-medium", isFa && "text-start")}>
          {label}
        </TableCell>
        <TableCell className={cn("tabular-nums", isFa && "text-start")}>
          <span dir="ltr" className="inline-block">
            {formatOverviewAmount(amt, isFa, rt("amountFree"))}
          </span>
        </TableCell>
        <TableCell className={cn(isFa && "text-start")}>
          <Badge variant={receiptStatusBadgeVariant(st)} className="font-normal">
            {receiptStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs", isFa && "text-start")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(r.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentReceipts")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
      isFa={isFa}
    >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")} isFa={isFa} />
      ) : (
        <OverviewDataTable
          isFa={isFa}
          headers={[tp("colUser"), tp("colAmount"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewPendingUsers({
  rows,
  isFa,
  onViewAll,
  onOpenUser,
}: {
  rows: DashRecord[]
  isFa: boolean
  onViewAll: () => void
  onOpenUser: (id: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenUser(id)}>
        <TableCell className={cn("font-medium", isFa && "text-start")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs", isFa && "text-start")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(u.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("pendingApprovals")}
      description={tp("pendingApprovalsHint")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
      isFa={isFa}
    >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")} isFa={isFa} />
      ) : (
        <OverviewDataTable
          isFa={isFa}
          headers={[tp("colUser"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentResellers({
  rows,
  isFa,
  onViewAll,
  onOpenReseller,
}: {
  rows: DashRecord[]
  isFa: boolean
  onViewAll: () => void
  onOpenReseller: (id: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)
  const statusLabel = (st: string) =>
    t(`usersAdmin.status_${st}`, { defaultValue: st || "—" })

  const tableRows = rows.map((u) => {
    const id = overviewNum(u.id)
    const st = String(u.status ?? "")
    return (
      <ClickableRow key={id} onClick={() => id > 0 && onOpenReseller(id)}>
        <TableCell className={cn("font-medium", isFa && "text-start")}>
          {userDisplayLabel(u)}
        </TableCell>
        <TableCell className={cn(isFa && "text-start")}>
          <Badge variant={userStatusBadgeVariant(st)} className="font-normal">
            {statusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell className={cn("tabular-nums", isFa && "text-start")}>
          <span dir="ltr" className="inline-block">
            {formatNumber(overviewNum(u.svc_count), isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentResellers")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
      isFa={isFa}
    >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")} isFa={isFa} />
      ) : (
        <OverviewDataTable
          isFa={isFa}
          headers={[tp("colUser"), tp("colStatus"), tp("colServices")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewRecentBroadcasts({
  rows,
  isFa,
  onViewAll,
  onOpenBroadcast,
}: {
  rows: DashRecord[]
  isFa: boolean
  onViewAll: () => void
  onOpenBroadcast: () => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`dashboardOverview.${k}`)

  const broadcastStatusLabel = (st: string) => {
    if (st === "sent") return t("broadcastAdmin.qs_sent")
    if (st === "pending") return t("broadcastAdmin.qs_pending")
    if (st === "sending") return t("broadcastAdmin.qs_sending")
    return st || "—"
  }

  const tableRows = rows.map((b) => {
    const id = overviewNum(b.id)
    const title = String(b.title ?? b.name ?? "").trim() || `#${id}`
    const st = String(b.status ?? "")
    return (
      <ClickableRow key={id} onClick={onOpenBroadcast}>
        <TableCell className={cn("max-w-[14rem] truncate font-medium", isFa && "text-start")}>
          {title}
        </TableCell>
        <TableCell className={cn(isFa && "text-start")}>
          <Badge variant="secondary" className="font-normal">
            {broadcastStatusLabel(st)}
          </Badge>
        </TableCell>
        <TableCell
          className={cn("text-muted-foreground tabular-nums text-xs", isFa && "text-start")}
        >
          <span dir="ltr" className="inline-block">
            {formatOverviewDate(b.created_at, isFa)}
          </span>
        </TableCell>
      </ClickableRow>
    )
  })

  return (
    <OverviewSectionCard
      title={tp("recentBroadcasts")}
      viewAllLabel={tp("viewAll")}
      onViewAll={onViewAll}
      isFa={isFa}
    >
      {tableRows.length === 0 ? (
        <OverviewEmpty message={tp("emptyPreview")} isFa={isFa} />
      ) : (
        <OverviewDataTable
          isFa={isFa}
          headers={[tp("colTitle"), tp("colStatus"), tp("colDate")]}
          rows={tableRows}
        />
      )}
    </OverviewSectionCard>
  )
}

export function OverviewPreviewGrid({
  isFa,
  isReseller,
  allowTab,
  recentUsers,
  recentReceipts,
  pendingUsersPreview,
  recentResellers,
  recentBroadcasts,
  onSelectTab,
  onOpenUserDetail,
  onOpenResellerWorkspace,
  onReceiptsFilterNavigate,
}: {
  isFa: boolean
  isReseller: boolean
  allowTab: (tab: string) => boolean
  recentUsers: DashRecord[]
  recentReceipts: DashRecord[]
  pendingUsersPreview: DashRecord[]
  recentResellers: DashRecord[]
  recentBroadcasts: DashRecord[]
  onSelectTab: (tabKey: string) => void
  onOpenUserDetail: (id: number) => void
  onOpenResellerWorkspace?: (id: number) => void
  onReceiptsFilterNavigate: (status?: string) => void
}) {
  const showUsers = allowTab("users")
  const showReceipts = allowTab("receipts")
  const showResellers =
    !isReseller && allowTab("resellers") && typeof onOpenResellerWorkspace === "function"
  const showBroadcast = allowTab("broadcast")

  if (!showUsers && !showReceipts && !showResellers && !showBroadcast) return null

  return (
    <section className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-2">
        {showUsers ? (
          <OverviewRecentUsers
            rows={recentUsers}
            isFa={isFa}
            onViewAll={() => onSelectTab("users")}
            onOpenUser={onOpenUserDetail}
          />
        ) : null}
        {showReceipts ? (
          <OverviewRecentReceipts
            rows={recentReceipts}
            isFa={isFa}
            onViewAll={() => onReceiptsFilterNavigate()}
            onOpenReceipt={(row) => {
              const st = String(row.status ?? "").toLowerCase()
              onReceiptsFilterNavigate(st === "pending" || st === "processing" ? st : undefined)
            }}
          />
        ) : null}
        {showUsers ? (
          <OverviewPendingUsers
            rows={pendingUsersPreview}
            isFa={isFa}
            onViewAll={() => onSelectTab("users")}
            onOpenUser={onOpenUserDetail}
          />
        ) : null}
        {showResellers ? (
          <OverviewRecentResellers
            rows={recentResellers}
            isFa={isFa}
            onViewAll={() => onSelectTab("resellers")}
            onOpenReseller={(id) => onOpenResellerWorkspace?.(id)}
          />
        ) : null}
      </div>
      {showBroadcast && recentBroadcasts.length > 0 ? (
        <OverviewRecentBroadcasts
          rows={recentBroadcasts}
          isFa={isFa}
          onViewAll={() => onSelectTab("broadcast")}
          onOpenBroadcast={() => onSelectTab("broadcast")}
        />
      ) : null}
    </section>
  )
}

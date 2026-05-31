"use client"

import { useCallback, useEffect, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { Search } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { DataPagination } from "@/components/data-pagination"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber, formatPlainLatinInt } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { DashboardUserMergeAdmin } from "@/components/dashboard-user-merge-admin"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function displayName(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  const un = String(u.username ?? "").trim()
  return un || "—"
}

function formatAtUsername(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (!s) return ""
  return s.startsWith("@") ? s : `@${s}`
}

/** Match backend digit normalization: numeric id / phone queries can be 1+ chars. */
function isDigitOnlyQuery(raw: string): boolean {
  const t = raw.replace(/\s/g, "")
  if (!t) return false
  return /^[\d۰-۹٠-٩]+$/.test(t)
}

function statusBadgeVariant(
  st: string
): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "pending") return "secondary"
  if (st === "rejected") return "destructive"
  if (st === "blocked") return "outline"
  return "outline"
}

function IdsCell({ numericId, username, emptyLabel }: { numericId: number; username: string; emptyLabel: string }) {
  if (numericId <= 0) return <span className="text-muted-foreground">{emptyLabel}</span>
  const at = formatAtUsername(username)
  return (
    <div className="space-y-0.5">
      <div dir="ltr" className="font-mono text-xs tabular-nums">
        {formatPlainLatinInt(numericId)}
      </div>
      {at ? <div className="text-xs text-muted-foreground">{at}</div> : null}
    </div>
  )
}

export function DashboardUsersAdmin({
  users,
  pending,
  usersPagination,
  pendingPagination,
  isFa,
  usersSearchQuery,
  onUsersSearchQueryChange,
  onMutateSuccess,
  onUsersPageChange,
  onUsersPerPageChange,
  onPendingPageChange,
  onPendingPerPageChange,
  onOpenUserDetail,
  isReseller = false,
  actorPermissions,
}: {
  users: DashRecord[]
  pending: DashRecord[]
  usersPagination: PaginationMeta | null
  pendingPagination: PaginationMeta | null
  isFa: boolean
  usersSearchQuery: string
  onUsersSearchQueryChange: (q: string) => void
  onMutateSuccess?: () => void
  onUsersPageChange: (page: number) => void
  onUsersPerPageChange: (perPage: number) => void
  onPendingPageChange: (page: number) => void
  onPendingPerPageChange: (perPage: number) => void
  onOpenUserDetail: (id: number) => void
  isReseller?: boolean
  actorPermissions?: Record<string, boolean>
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`usersAdmin.${k}`)
  const statusLabel = (st: string) => t(`usersAdmin.status_${st}`, { defaultValue: st })

  const [busyId, setBusyId] = useState<number | null>(null)
  const [alertText, setAlertText] = useState<string | null>(null)
  const [mergeOpen, setMergeOpen] = useState(false)
  const [searchDraft, setSearchDraft] = useState(usersSearchQuery)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const canManageUsers = !isReseller || actorPermissions?.["users.manage"] !== false
  const canMergeUsers = !isReseller

  useEffect(() => {
    setSearchDraft(usersSearchQuery)
  }, [usersSearchQuery])

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      const effective =
        next !== "" && !isDigitOnlyQuery(next) && next.length < 2 ? "" : next
      if (effective !== usersSearchQuery.trim()) {
        onUsersSearchQueryChange(effective)
      }
    }, 580)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [searchDraft, usersSearchQuery, onUsersSearchQueryChange])

  const runMembership = useCallback(
    async (userId: number, action: "approve" | "reject") => {
      setBusyId(userId)
      setAlertText(null)
      try {
        const res = await postAdminMutate("membership", {
          membership_user_id: userId,
          svp_user_membership_action: action,
        })
        if (!res.ok) {
          const parts = [res.message, res.reason].filter(Boolean)
          setAlertText(parts.length ? parts.join(" — ") : tp("mutateError"))
          return
        }
        onMutateSuccess?.()
      } finally {
        setBusyId(null)
      }
    },
    [onMutateSuccess, tp]
  )

  const uname = (u: DashRecord) => String(u.username ?? "")

  const renderUserCard = (u: DashRecord, showActions: boolean, showManage?: boolean) => {
    const id = num(u.id)
    const st = String(u.status ?? "")
    const tg = num(u.tg_user_id)
    const bl = num(u.bale_user_id)
    return (
      <Card>
        <CardHeader className="space-y-1 pb-2">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
              <CardTitle className="text-base">{displayName(u)}</CardTitle>
              <CardDescription dir="ltr" className="font-mono text-xs">
                #{formatPlainLatinInt(id)}
              </CardDescription>
            </div>
            <Badge variant={statusBadgeVariant(st)}>{statusLabel(st)}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span>
              {tp("colServices")}: {formatNumber(num(u.svc_count), isFa)}
            </span>
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            <div>
              <div className="mb-0.5 text-xs font-medium text-muted-foreground">{tp("colTelegram")}</div>
              <IdsCell numericId={tg} username={uname(u)} emptyLabel="—" />
            </div>
            <div>
              <div className="mb-0.5 text-xs font-medium text-muted-foreground">{tp("colBale")}</div>
              <IdsCell numericId={bl} username={uname(u)} emptyLabel="—" />
            </div>
          </div>
          {showActions ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <Button
                type="button"
                size="sm"
                disabled={busyId === id}
                onClick={() => void runMembership(id, "approve")}
              >
                {tp("approve")}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={busyId === id}
                onClick={() => void runMembership(id, "reject")}
              >
                {tp("reject")}
              </Button>
            </div>
          ) : null}
          {showManage ? (
            <div className="pt-2">
              <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(id)}>
                {tp("manage")}
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>
    )
  }

  return (
    <div className={dashPageRootClass(isFa, "space-y-8")} dir={dashDir(isFa)}>
      <DashboardPageHeader
        title={tp("title")}
        description={tp("subtitle")}
        actions={
          canMergeUsers ? (
            <Dialog open={mergeOpen} onOpenChange={setMergeOpen}>
              <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                  {tp("mergeUsers")}
                </Button>
              </DialogTrigger>
              <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl" dir={dashDir(isFa)}>
                <DialogHeader>
                  <DialogTitle>{tp("mergeUsers")}</DialogTitle>
                </DialogHeader>
                <DashboardUserMergeAdmin
                  isFa={isFa}
                  onMutateSuccess={() => {
                    onMutateSuccess?.()
                    setMergeOpen(false)
                  }}
                />
              </DialogContent>
            </Dialog>
          ) : null
        }
      />

      {alertText ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {alertText}
        </div>
      ) : null}

      <section className="space-y-3">
        <h3 className="text-base font-medium">{tp("pendingSection")}</h3>
        {pending.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("pendingEmpty")}</p>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {pending.map((u) => (
              <li key={String(u.id ?? "")}>{renderUserCard(u, canManageUsers, true)}</li>
            ))}
          </ul>
        )}
        <DataPagination
          meta={pendingPagination}
          isFa={isFa}
          onPageChange={onPendingPageChange}
          onPerPageChange={onPendingPerPageChange}
        />
      </section>

      <Separator />

      <section className="space-y-3">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h3 className="text-base font-medium">{tp("allUsersSection")}</h3>
          {usersPagination ? (
            <p className="text-xs text-muted-foreground">
              {t("usersAdmin.listPaginationHint", { total: formatNumber(usersPagination.total, isFa) })}
            </p>
          ) : null}
        </div>
        <div className={cn("space-y-1.5", isFa && "text-right")}>
          <div className="relative max-w-md">
            <Search
              className={cn(
                "pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground",
                isFa ? "end-3" : "start-3"
              )}
              aria-hidden
            />
            <Input
              type="search"
              value={searchDraft}
              onChange={(e) => setSearchDraft(e.target.value)}
              placeholder={tp("searchPlaceholder")}
              className={cn(isFa ? "pe-9" : "ps-9")}
              autoComplete="off"
            />
          </div>
          <p className="text-xs text-muted-foreground">{tp("searchHint")}</p>
        </div>
        {users.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("usersEmpty")}</p>
        ) : (
          <div
            className={cn(
              "w-full max-w-full overflow-x-auto rounded-md border border-border",
              isFa && "text-right"
            )}
          >
            <table
              className={cn(
                "w-full min-w-[42rem] border-collapse text-sm [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
                "text-start"
              )}
            >
              <thead>
                <tr className="bg-muted/40">
                  <th className="p-2 font-medium">{tp("colId")}</th>
                  <th className="p-2 font-medium">{tp("colName")}</th>
                  <th className="p-2 font-medium">{tp("colStatus")}</th>
                  <th className="p-2 font-medium">{tp("colServices")}</th>
                  <th className="p-2 font-medium">{tp("colTelegram")}</th>
                  <th className="p-2 font-medium">{tp("colBale")}</th>
                  <th className="p-2 font-medium">{tp("colActions")}</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => {
                  const id = num(u.id)
                  const st = String(u.status ?? "")
                  const tg = num(u.tg_user_id)
                  const bl = num(u.bale_user_id)
                  return (
                    <tr key={id}>
                      <td dir="ltr" className="p-2 font-mono text-xs tabular-nums">
                        {formatPlainLatinInt(id)}
                      </td>
                      <td className="p-2">{displayName(u)}</td>
                      <td className="p-2">
                        <Badge variant={statusBadgeVariant(st)} className="font-normal">
                          {statusLabel(st)}
                        </Badge>
                      </td>
                      <td className="p-2 tabular-nums">{formatNumber(num(u.svc_count), isFa)}</td>
                      <td className="p-2 align-top">
                        <IdsCell numericId={tg} username={uname(u)} emptyLabel="—" />
                      </td>
                      <td className="p-2 align-top">
                        <IdsCell numericId={bl} username={uname(u)} emptyLabel="—" />
                      </td>
                      <td className="p-2 align-top">
                        <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(id)}>
                          {tp("manage")}
                        </Button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
        <DataPagination
          meta={usersPagination}
          isFa={isFa}
          onPageChange={onUsersPageChange}
          onPerPageChange={onUsersPerPageChange}
        />
      </section>
    </div>
  )
}

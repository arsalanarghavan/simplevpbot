"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { DataPagination } from "@/components/data-pagination"
import { getAdminJson } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { formatDateTime } from "@/lib/format-locale"
import { useAdminTp } from "@/lib/use-admin-tp"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"

type AuditRow = {
  id: number
  created_at: string
  domain: string
  event_type: string
  actor_kind: string
  actor_wp_user_id: number
  actor_svp_user_id: number
  target_type: string
  target_id: number
  reseller_scope_id: number
  payload: unknown
}

const DOMAIN_OPTIONS = ["admin", "billing", "bot", "security", "reseller"] as const

export function DashboardAuditAdmin({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = useAdminTp("auditAdmin")
  const [rows, setRows] = useState<AuditRow[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [domain, setDomain] = useState("")
  const [eventType, setEventType] = useState("")
  const [search, setSearch] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/audit", {
        page,
        per_page: perPage,
        domain,
        event_type: eventType,
        q: search,
      })
      if (!json.ok) {
        setError(String(json.message || t("auditAdmin.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as AuditRow[]) : []
      setRows(list)
      setPagination(parsePaginationMeta(json.pagination))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, domain, eventType, search])

  useEffect(() => {
    void load()
  }, [page, perPage, domain, eventType, search, load])

  const payloadPreview = (p: unknown) => {
    if (!p || (typeof p === "object" && Object.keys(p as object).length === 0)) return "—"
    try {
      const s = JSON.stringify(p)
      return s.length > 80 ? `${s.slice(0, 80)}…` : s
    } catch {
      return "—"
    }
  }

  return (
    <div className={dashPageRootClass(isFa, "space-y-4")} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      <div className={cn("flex flex-wrap items-end gap-3")}>
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("filterDomain")}</Label>
          <Select value={domain || "__all"} onValueChange={(v) => setDomain(v === "__all" ? "" : v)}>
            <SelectTrigger className="w-[140px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all">{tp("domainAll")}</SelectItem>
              {DOMAIN_OPTIONS.map((d) => (
                <SelectItem key={d} value={d}>
                  {d}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("filterEvent")}</Label>
          <Input
            value={eventType}
            onChange={(e) => setEventType(e.target.value)}
            placeholder={tp("eventPlaceholder")}
            className="h-9 w-[180px]"
            dir="ltr"
          />
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("search")}</Label>
          <div className="flex gap-2">
            <Input
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder={tp("searchPlaceholder")}
              className="h-9 w-[200px]"
              dir="ltr"
            />
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => {
                setSearch(searchInput.trim())
                setPage(1)
              }}
            >
              {tp("searchBtn")}
            </Button>
          </div>
        </div>
      </div>

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{tp("colTime")}</TableHead>
              <TableHead>{tp("colDomain")}</TableHead>
              <TableHead>{tp("colEvent")}</TableHead>
              <TableHead>{tp("colActor")}</TableHead>
              <TableHead>{tp("colTarget")}</TableHead>
              <TableHead>{tp("colPayload")}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading && rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  {tp("loading")}
                </TableCell>
              </TableRow>
            ) : null}
            {!loading && rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  {tp("empty")}
                </TableCell>
              </TableRow>
            ) : null}
            {rows.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="whitespace-nowrap text-xs" dir="ltr">
                  {formatDateTime(row.created_at, isFa)}
                </TableCell>
                <TableCell className="text-xs">{row.domain || "—"}</TableCell>
                <TableCell className="font-mono text-xs" dir="ltr">
                  {row.event_type || "—"}
                </TableCell>
                <TableCell className="text-xs" dir="ltr">
                  {row.actor_kind}
                  {row.actor_svp_user_id > 0 ? ` #${row.actor_svp_user_id}` : ""}
                  {row.actor_wp_user_id > 0 && row.actor_svp_user_id < 1 ? ` wp:${row.actor_wp_user_id}` : ""}
                </TableCell>
                <TableCell className="text-xs" dir="ltr">
                  {row.target_type ? `${row.target_type}:${row.target_id}` : "—"}
                </TableCell>
                <TableCell className="max-w-[240px] truncate text-xs text-muted-foreground" dir="ltr">
                  {payloadPreview(row.payload)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        meta={pagination}
        isFa={isFa}
        onPageChange={setPage}
        onPerPageChange={(n) => {
          setPerPage(n)
          setPage(1)
        }}
      />
    </div>
  )
}

"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { useAdminTp } from "@/lib/use-admin-tp"
import { cn } from "@/lib/utils"

type LogRow = {
  id: number
  level: string
  message: string
  context: unknown
  created_at: string
}

export function SiteSettingsLogsTab({ isFa }: { isFa: boolean }) {
  const { t } = useTranslation()
  const tp = useAdminTp("siteSettings.logs")
  const [rows, setRows] = useState<LogRow[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [level, setLevel] = useState("")
  const [search, setSearch] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [detail, setDetail] = useState<LogRow | null>(null)
  const [clearDays, setClearDays] = useState("30")
  const [clearing, setClearing] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/logs", {
        page,
        per_page: perPage,
        level,
        q: search,
      })
      if (!json.ok) {
        setError(String(json.message || t("siteSettings.logs.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as LogRow[]) : []
      setRows(list)
      setPagination(parsePaginationMeta(json.pagination))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, level, search])

  useEffect(() => {
    void load()
  }, [page, perPage, level, search, load])

  const contextPreview = useMemo(() => {
    if (!detail?.context) return "—"
    try {
      return JSON.stringify(detail.context, null, 2)
    } catch {
      return String(detail.context)
    }
  }, [detail])

  const onClear = useCallback(async (all: boolean) => {
    setClearing(true)
    setError(null)
    try {
      const res = await postAdminMutate("logs_clear", {
        confirm: 1,
        older_than_days: all ? 0 : Math.max(0, Number(clearDays) || 0),
      })
      if (!res.ok) {
        setError(res.message || tp("clearError"))
        return
      }
      setPage(1)
      await load()
    } finally {
      setClearing(false)
    }
  }, [clearDays, load, tp])

  return (
    <div className={cn("space-y-4", isFa && "text-right")}>
      <div className={cn("flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between")}>
        <div className={cn("flex flex-wrap gap-2")}>
          <div className="space-y-1">
            <Label className="text-xs">{tp("filterLevel")}</Label>
            <Select value={level || "all"} onValueChange={(v) => { setLevel(v === "all" ? "" : v); setPage(1) }}>
              <SelectTrigger className="w-[140px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{tp("levelAll")}</SelectItem>
                <SelectItem value="info">info</SelectItem>
                <SelectItem value="warning">warning</SelectItem>
                <SelectItem value="error">error</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label className="text-xs">{tp("search")}</Label>
            <div className="flex gap-2">
              <Input
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder={tp("searchPlaceholder")}
                className="w-48"
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    setSearch(searchInput.trim())
                    setPage(1)
                  }
                }}
              />
              <Button type="button" variant="secondary" onClick={() => { setSearch(searchInput.trim()); setPage(1) }}>
                {tp("searchBtn")}
              </Button>
            </div>
          </div>
        </div>
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button type="button" variant="destructive" disabled={clearing}>
              {tp("clearBtn")}
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent className={cn(isFa && "text-right [direction:rtl]")}>
            <AlertDialogHeader className={cn(isFa && "text-right sm:text-right")}>
              <AlertDialogTitle>{tp("clearTitle")}</AlertDialogTitle>
              <AlertDialogDescription>{tp("clearDesc")}</AlertDialogDescription>
            </AlertDialogHeader>
            <div className="space-y-2">
              <Label htmlFor="clear_days">{tp("clearOlderDays")}</Label>
              <Input
                id="clear_days"
                type="number"
                min={0}
                value={clearDays}
                onChange={(e) => setClearDays(e.target.value)}
                dir="ltr"
              />
              <p className="text-xs text-muted-foreground">{tp("clearOlderHint")}</p>
            </div>
            <AlertDialogFooter className="gap-2">
              <AlertDialogCancel>{tp("cancel")}</AlertDialogCancel>
              <AlertDialogAction onClick={() => void onClear(false)} disabled={clearing}>
                {tp("clearConfirm")}
              </AlertDialogAction>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={() => void onClear(true)}
                disabled={clearing}
              >
                {tp("clearAll")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}

      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-16">ID</TableHead>
              <TableHead className="w-24">{tp("colLevel")}</TableHead>
              <TableHead>{tp("colMessage")}</TableHead>
              <TableHead className="w-40">{tp("colTime")}</TableHead>
              <TableHead className="w-24" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading && rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  {tp("loading")}
                </TableCell>
              </TableRow>
            ) : null}
            {!loading && rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  {tp("empty")}
                </TableCell>
              </TableRow>
            ) : null}
            {rows.map((r) => (
              <TableRow key={r.id}>
                <TableCell className="font-mono text-xs">{r.id}</TableCell>
                <TableCell className="font-mono text-xs">{r.level}</TableCell>
                <TableCell className="max-w-md truncate text-sm">{r.message}</TableCell>
                <TableCell className="text-xs text-muted-foreground whitespace-nowrap">{r.created_at}</TableCell>
                <TableCell>
                  <Button type="button" size="sm" variant="ghost" onClick={() => setDetail(r)}>
                    {tp("details")}
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {pagination ? (
        <DataPagination
          meta={pagination}
          isFa={isFa}
          onPageChange={setPage}
          onPerPageChange={(n) => {
            setPerPage(n)
            setPage(1)
          }}
        />
      ) : null}

      <Dialog open={detail != null} onOpenChange={(o) => !o && setDetail(null)}>
        <DialogContent className={cn("sm:max-w-2xl", isFa && "text-right [direction:rtl]")}>
          <DialogHeader className={cn(isFa && "text-right sm:text-right")}>
            <DialogTitle>{tp("detailTitle")}</DialogTitle>
          </DialogHeader>
          {detail ? (
            <div className="space-y-3 text-sm">
              <p>
                <span className="text-muted-foreground">{tp("colLevel")}: </span>
                {detail.level}
              </p>
              <p className="whitespace-pre-wrap break-words">{detail.message}</p>
              <pre className="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs" dir="ltr">
                {contextPreview}
              </pre>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  )
}

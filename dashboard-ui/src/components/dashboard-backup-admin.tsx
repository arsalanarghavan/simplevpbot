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
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { getAdminJson, postAdminFormData, postAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { useAdminTp } from "@/lib/use-admin-tp"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

type BackupRow = {
  filename: string
  size_bytes: number
  created_at: string
  has_panel_db: boolean
  panel_db_status?: string
  panel_db_detail?: string
}

type PanelOption = {
  id: number
  label: string
}

type DbInboundRow = {
  id: number
  remark?: string
  port?: number
  protocol?: string
  service_count?: number
  on_panel_now?: boolean
}

type PanelInboundRow = {
  id: number
  remark?: string
  port?: number
  protocol?: string
}

type RebuildTotals = {
  created?: number
  patched?: number
  skipped?: number
  failed?: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function formatBytes(bytes: number, isFa: boolean): string {
  if (bytes < 1024) return `${formatNumber(bytes, isFa)} B`
  if (bytes < 1024 * 1024) return `${formatNumber(Math.round(bytes / 1024), isFa)} KB`
  return `${formatNumber(Math.round((bytes / (1024 * 1024)) * 10) / 10, isFa)} MB`
}

function tsLabel(unix: number, isFa: boolean): string {
  if (unix < 1) return "—"
  return formatDateTime(new Date(unix * 1000).toISOString(), isFa)
}

type RestoreStats = {
  users_matched?: number
  users_inserted?: number
  users_skipped?: number
  errors?: unknown[]
  panel_restore?: { ok_count?: number; fail_count?: number }
}

function panelDbListLabel(
  row: BackupRow,
  tp: (k: string, o?: Record<string, string | number>) => string): string {
  const status = String(row.panel_db_status ?? (row.has_panel_db ? "full" : "none"))
  if (status === "full") return tp("panelYes")
  if (status === "partial") {
    const detail = String(row.panel_db_detail ?? "").trim()
    return detail ? `${tp("panelPartial")}: ${detail}` : tp("panelPartial")
  }
  if (status === "none") {
    const detail = String(row.panel_db_detail ?? "").trim()
    return detail ? `${tp("panelNoneFailed")}: ${detail}` : tp("panelNoneFailed")
  }
  if (status === "na") return tp("panelNa")
  return row.has_panel_db ? tp("panelYes") : tp("panelNo")
}

function formatRestoreReport(data: unknown, tp: (k: string, o?: Record<string, string | number>) => string): string {
  const d = data && typeof data === "object" && !Array.isArray(data) ? (data as RestoreStats) : null
  if (!d) return ""
  const lines: string[] = [
    tp("restoreReportUsers", {
      matched: Number(d.users_matched ?? 0),
      inserted: Number(d.users_inserted ?? 0),
      skipped: Number(d.users_skipped ?? 0),
    }),
  ]
  const pr = d.panel_restore
  if (pr && typeof pr === "object") {
    lines.push(
      tp("restoreReportPanel", {
        ok: Number(pr.ok_count ?? 0),
        fail: Number(pr.fail_count ?? 0),
      }))
  }
  const errN = Array.isArray(d.errors) ? d.errors.length : 0
  if (errN > 0) {
    lines.push(tp("restoreReportErrors", { n: errN }))
  }
  return lines.join("\n")
}

export function DashboardBackupAdmin({
  settings,
  isFa,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tp = useAdminTp("backupAdmin")
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      backup_interval_minutes: String(Math.max(5, num(s.backup_interval_minutes) || 60)),
      backup_telegram_chat_id: String(num(s.backup_telegram_chat_id)),
      backup_bale_chat_id: String(num(s.backup_bale_chat_id)),
      backup_send_telegram_admins: bool(s.backup_send_telegram_admins),
      backup_send_bale_admins: bool(s.backup_send_bale_admins),
      backup_send_telegram_channel: bool(s.backup_send_telegram_channel),
      backup_send_bale_channel: bool(s.backup_send_bale_channel),
      backup_store_on_site: bool(s.backup_store_on_site),
      backup_site_retention_count: String(Math.max(1, Math.min(500, num(s.backup_site_retention_count) || 14))),
      backup_max_zip_mb: String(Math.max(0, num(s.backup_max_zip_mb))),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [backupRows, setBackupRows] = useState<BackupRow[]>([])
  const [storeOnSiteLive, setStoreOnSiteLive] = useState(bool(s.backup_store_on_site))
  const [lastBackupAt, setLastBackupAt] = useState(0)
  const [lastBuiltAt, setLastBuiltAt] = useState(0)
  const [listLoading, setListLoading] = useState(false)
  const [listError, setListError] = useState<string | null>(null)
  const [backupRunning, setBackupRunning] = useState(false)
  const [backupMsg, setBackupMsg] = useState<string | null>(null)
  const [restoreTarget, setRestoreTarget] = useState<BackupRow | null>(null)
  const [restorePanelDb, setRestorePanelDb] = useState(false)
  const [restoreBusy, setRestoreBusy] = useState(false)
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadConfirm, setUploadConfirm] = useState(false)
  const [uploadRestorePanelDb, setUploadRestorePanelDb] = useState(false)
  const [uploadBusy, setUploadBusy] = useState(false)
  const [uploadMsg, setUploadMsg] = useState<string | null>(null)

  const [panelOptions, setPanelOptions] = useState<PanelOption[]>([])
  const [rebuildPanelId, setRebuildPanelId] = useState("0")
  const [rebuildDryRun, setRebuildDryRun] = useState(false)
  const [rebuildOpen, setRebuildOpen] = useState(false)
  const [rebuildBusy, setRebuildBusy] = useState(false)
  const [rebuildMsg, setRebuildMsg] = useState<string | null>(null)
  const [rebuildProgress, setRebuildProgress] = useState({ done: 0, total: 0 })
  const [inboundMapLoading, setInboundMapLoading] = useState(false)
  const [inboundMapError, setInboundMapError] = useState<string | null>(null)
  const [inboundMapMsg, setInboundMapMsg] = useState<string | null>(null)
  const [dbInbounds, setDbInbounds] = useState<DbInboundRow[]>([])
  const [panelInbounds, setPanelInbounds] = useState<PanelInboundRow[]>([])
  const [inboundMapDraft, setInboundMapDraft] = useState<Record<string, string>>({})
  const [inboundMapMissing, setInboundMapMissing] = useState(0)
  const [fix51200Count, setFix51200Count] = useState<number | null>(null)
  const [fix51200Open, setFix51200Open] = useState(false)
  const [fix51200Busy, setFix51200Busy] = useState(false)
  const [fix51200Msg, setFix51200Msg] = useState<string | null>(null)
  const [resellerBackfillBusy, setResellerBackfillBusy] = useState(false)
  const [resellerBackfillResult, setResellerBackfillResult] = useState<string | null>(null)

  const inboundRowLabel = useCallback(
    (row: { id: number; remark?: string; port?: number; protocol?: string }, count?: number) =>
      tp("inboundMapRowHint", {
        id: row.id,
        protocol: String(row.protocol ?? "—"),
        port: num(row.port),
        remark: String(row.remark ?? "—"),
        count: count ?? 0,
      }),
    [tp])

  const buildInboundMapPayload = useCallback(() => {
    const out: Record<string, number> = {}
    for (const row of dbInbounds) {
      const old = row.id
      const neu = num(inboundMapDraft[String(old)] || old)
      if (neu > 0) {
        out[String(old)] = neu
      }
    }
    return out
  }, [dbInbounds, inboundMapDraft])

  const loadInboundMap = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) {
      setDbInbounds((prev) => (prev.length === 0 ? prev : []))
      setPanelInbounds((prev) => (prev.length === 0 ? prev : []))
      setInboundMapDraft((prev) => (Object.keys(prev).length === 0 ? prev : {}))
      setInboundMapMissing((prev) => (prev === 0 ? prev : 0))
      return
    }
    setInboundMapLoading(true)
    setInboundMapError(null)
    setInboundMapMsg(null)
    try {
      const json = await getAdminJson("/dashboard/admin/panel/inbound-map", { panel_id: pid })
      if (!json.ok) {
        setInboundMapError(String(json.message || t("backupAdmin.inboundMapLoadError")))
        return
      }
      const db = Array.isArray(json.db_inbounds) ? (json.db_inbounds as DbInboundRow[]) : []
      const live = Array.isArray(json.panel_inbounds) ? (json.panel_inbounds as PanelInboundRow[]) : []
      const stored = json.map && typeof json.map === "object" ? (json.map as Record<string, number>) : {}
      const suggest =
        json.suggested_map && typeof json.suggested_map === "object"
          ? (json.suggested_map as Record<string, number>)
          : {}
      const draft: Record<string, string> = {}
      for (const row of db) {
        const old = String(row.id)
        const fromStore = stored[row.id] ?? stored[Number(old)]
        const fromSuggest = suggest[row.id] ?? suggest[Number(old)]
        const pick = fromStore ?? fromSuggest ?? row.id
        draft[old] = String(pick)
      }
      setDbInbounds(db)
      setPanelInbounds(live)
      setInboundMapDraft(draft)
      setInboundMapMissing(Array.isArray(json.missing_on_panel) ? json.missing_on_panel.length : 0)
    } finally {
      setInboundMapLoading(false)
    }
  }, [rebuildPanelId])

  useEffect(() => {
    void loadInboundMap()
  }, [rebuildPanelId, loadInboundMap])

  const refreshFix51200Count = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) {
      setFix51200Count(null)
      return
    }
    try {
      const json = await postAdminJson("/dashboard/admin/panel/fix-51200-traffic", {
        panel_id: pid,
        dry_run: true,
        offset: 0,
      })
      if (json.ok) {
        setFix51200Count(num(json.total))
      }
    } catch {
      setFix51200Count(null)
    }
  }, [rebuildPanelId])

  useEffect(() => {
    void refreshFix51200Count()
  }, [refreshFix51200Count])

  const applyInboundSuggest = useCallback(() => {
    setInboundMapDraft((prev) => {
      const next = { ...prev }
      for (const row of dbInbounds) {
        const live = panelInbounds.find(
          (p) =>
            String(p.remark ?? "").toLowerCase() === String(row.remark ?? "").toLowerCase() &&
            num(p.port) === num(row.port) &&
            String(p.protocol ?? "").toLowerCase() === String(row.protocol ?? "").toLowerCase())
        if (live) {
          next[String(row.id)] = String(live.id)
          continue
        }
        const byRemark = panelInbounds.filter(
          (p) => String(p.remark ?? "").toLowerCase() === String(row.remark ?? "").toLowerCase())
        if (byRemark.length === 1) {
          next[String(row.id)] = String(byRemark[0].id)
        }
      }
      return next
    })
  }, [dbInbounds, panelInbounds])

  const saveInboundMap = useCallback(
    async (applyToDb: boolean) => {
      const pid = num(rebuildPanelId)
      if (pid < 1) return
      setInboundMapLoading(true)
      setInboundMapMsg(null)
      setInboundMapError(null)
      try {
        const json = await postAdminJson("/dashboard/admin/panel/inbound-map", {
          panel_id: pid,
          map: buildInboundMapPayload(),
          apply_to_db: applyToDb ? 1 : 0,
        })
        if (!json.ok) {
          setInboundMapError(String(json.message || tp("saveError")))
          return
        }
        if (applyToDb && json.db_counts && typeof json.db_counts === "object") {
          const c = json.db_counts as { services?: number; plans?: number }
          setInboundMapMsg(
            tp("inboundMapSaveDbOk", {
              services: num(c.services),
              plans: num(c.plans),
            }))
        } else {
          setInboundMapMsg(tp("inboundMapSaved"))
        }
        await loadInboundMap()
      } finally {
        setInboundMapLoading(false)
      }
    },
    [buildInboundMapPayload, loadInboundMap, rebuildPanelId, tp])

  const loadBackups = useCallback(async () => {
    setListLoading(true)
    setListError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/backups", {})
      if (!json.ok) {
        setListError(String(json.message || t("backupAdmin.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as BackupRow[]) : []
      setBackupRows(list)
      const panels = Array.isArray(json.panels) ? (json.panels as PanelOption[]) : []
      setPanelOptions(
        panels
          .map((p) => ({ id: num(p.id), label: String(p.label ?? `#${num(p.id)}`) }))
          .filter((p) => p.id > 0))
      setStoreOnSiteLive(bool(json.store_on_site))
      setLastBackupAt(num(json.last_backup_at))
      setLastBuiltAt(num(json.last_built_at))
    } finally {
      setListLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadBackups()
  }, [loadBackups])

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "backup",
        backup_interval_minutes: num(form.backup_interval_minutes),
        backup_telegram_chat_id: num(form.backup_telegram_chat_id),
        backup_bale_chat_id: num(form.backup_bale_chat_id),
        backup_send_telegram_admins: form.backup_send_telegram_admins ? 1 : 0,
        backup_send_bale_admins: form.backup_send_bale_admins ? 1 : 0,
        backup_send_telegram_channel: form.backup_send_telegram_channel ? 1 : 0,
        backup_send_bale_channel: form.backup_send_bale_channel ? 1 : 0,
        backup_store_on_site: form.backup_store_on_site ? 1 : 0,
        backup_site_retention_count: Math.max(1, Math.min(500, num(form.backup_site_retention_count))),
        backup_max_zip_mb: Math.max(0, num(form.backup_max_zip_mb)),
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      setStoreOnSiteLive(form.backup_store_on_site)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const onBackupNow = useCallback(async () => {
    setBackupRunning(true)
    setBackupMsg(null)
    try {
      const json = await postAdminJson("/dashboard/admin/backup/run", {})
      if (!json.ok) {
        setBackupMsg(String(json.message || tp("backupNowError")))
        return
      }
      const data = json.data as { message?: string; panel_db_warning?: string } | undefined
      const parts = [
        typeof data?.message === "string" ? data.message : tp("backupNowSuccess"),
        typeof data?.panel_db_warning === "string" && data.panel_db_warning
          ? tp("backupPanelWarning", { warning: data.panel_db_warning })
          : "",
      ].filter(Boolean)
      setBackupMsg(parts.join("\n"))
      await loadBackups()
    } finally {
      setBackupRunning(false)
    }
  }, [loadBackups, tp])

  const onRestoreFile = useCallback(async () => {
    if (!restoreTarget) return
    setRestoreBusy(true)
    setListError(null)
    try {
      const json = await postAdminJson("/dashboard/admin/backup/restore", {
        filename: restoreTarget.filename,
        confirm: true,
        restore_panel_db: restorePanelDb ? 1 : 0,
      })
      if (!json.ok) {
        setListError(String(json.message || tp("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, tp)
      setBackupMsg([String(json.message || tp("restoreSuccess")), report].filter(Boolean).join("\n"))
      setRestoreTarget(null)
      setRestorePanelDb(false)
      onMutateSuccess?.()
    } finally {
      setRestoreBusy(false)
    }
  }, [onMutateSuccess, restorePanelDb, restoreTarget, tp])

  const onUploadRestore = useCallback(async () => {
    if (!uploadFile || !uploadConfirm) return
    setUploadBusy(true)
    setUploadMsg(null)
    try {
      const fd = new FormData()
      fd.append("confirm", "1")
      if (uploadRestorePanelDb) {
        fd.append("restore_panel_db", "1")
      }
      fd.append("file", uploadFile)
      const json = await postAdminFormData("/dashboard/admin/backup/restore-upload", fd)
      if (!json.ok) {
        setUploadMsg(String(json.message || tp("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, tp)
      setUploadMsg([String(json.message || tp("restoreSuccess")), report].filter(Boolean).join("\n"))
      setUploadFile(null)
      setUploadConfirm(false)
      setUploadRestorePanelDb(false)
      onMutateSuccess?.()
    } finally {
      setUploadBusy(false)
    }
  }, [onMutateSuccess, uploadConfirm, uploadFile, uploadRestorePanelDb, tp])

  const onRebuildPanels = useCallback(async () => {
    setRebuildBusy(true)
    setRebuildMsg(null)
    setRebuildProgress({ done: 0, total: 0 })
    const totals: RebuildTotals = { created: 0, patched: 0, skipped: 0, failed: 0 }
    const errSamples: string[] = []
    let offset = 0
    let total = 0
    const pid = num(rebuildPanelId)
    const mapPayload = pid > 0 ? buildInboundMapPayload() : undefined
    try {
      for (;;) {
        const body: Record<string, unknown> = {
          confirm: !rebuildDryRun,
          dry_run: rebuildDryRun,
          panel_id: pid,
          offset,
        }
        if (mapPayload && Object.keys(mapPayload).length > 0) {
          body.inbound_map = mapPayload
        }
        const json = await postAdminJson("/dashboard/admin/panel/rebuild-from-db", body)
        if (!json.ok) {
          setRebuildMsg(String(json.message || tp("rebuildError")))
          return
        }
        total = num(json.total)
        const batch = json.totals as RebuildTotals | undefined
        if (batch) {
          totals.created = num(totals.created) + num(batch.created)
          totals.patched = num(totals.patched) + num(batch.patched)
          totals.skipped = num(totals.skipped) + num(batch.skipped)
          totals.failed = num(totals.failed) + num(batch.failed)
        }
        const batchErrs = Array.isArray(json.errors) ? json.errors : []
        for (const e of batchErrs) {
          if (errSamples.length >= 8 || !e || typeof e !== "object") continue
          const row = e as { email?: string; reason?: string }
          const line = `${String(row.email ?? "?")}: ${String(row.reason ?? "?")}`
          if (!errSamples.includes(line)) errSamples.push(line)
        }
        offset = num(json.next_offset)
        setRebuildProgress({ done: offset, total })
        if (bool(json.done)) {
          break
        }
      }
      setRebuildMsg(
        [
          tp("rebuildReport", {
            created: num(totals.created),
            patched: num(totals.patched),
            skipped: num(totals.skipped),
            failed: num(totals.failed),
          }),
          errSamples.length > 0 ? errSamples.join("\n") : "",
          rebuildDryRun ? "" : tp("rebuildDone"),
        ]
          .filter(Boolean)
          .join("\n"))
      setRebuildOpen(false)
      if (!rebuildDryRun) onMutateSuccess?.()
    } finally {
      setRebuildBusy(false)
    }
  }, [buildInboundMapPayload, onMutateSuccess, rebuildDryRun, rebuildPanelId, tp])

  const onFix51200 = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) return
    setFix51200Busy(true)
    setFix51200Msg(null)
    const totals = { fixed: 0, skipped: 0, failed: 0, noSource: 0 }
    let offset = 0
    try {
      for (;;) {
        const json = await postAdminJson("/dashboard/admin/panel/fix-51200-traffic", {
          confirm: true,
          panel_id: pid,
          offset,
          inbound_map: buildInboundMapPayload(),
        })
        if (!json.ok) {
          setFix51200Msg(String(json.message || tp("saveError")))
          return
        }
        const batch = json.totals as {
          fixed?: number
          skipped?: number
          failed?: number
          no_source?: number
        } | undefined
        if (batch) {
          totals.fixed += num(batch.fixed)
          totals.skipped += num(batch.skipped)
          totals.failed += num(batch.failed)
          totals.noSource += num(batch.no_source)
        }
        offset = num(json.next_offset)
        if (bool(json.done)) break
      }
      setFix51200Msg(
        [
          totals.fixed < 1 && totals.failed < 1 ? tp("fix51200None") : "",
          tp("fix51200Report", {
            fixed: totals.fixed,
            skipped: totals.skipped,
            noSource: totals.noSource,
            failed: totals.failed,
          }),
          tp("fix51200Done"),
        ]
          .filter(Boolean)
          .join("\n"))
      setFix51200Open(false)
      await refreshFix51200Count()
      onMutateSuccess?.()
    } finally {
      setFix51200Busy(false)
    }
  }, [buildInboundMapPayload, onMutateSuccess, refreshFix51200Count, rebuildPanelId, tp])

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className={cn("flex items-center gap-2 text-sm")} dir={dashDir(isFa)}>
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {tp(labelKey)}
    </label>
  )

  return (
    <div className={dashPageRootClass(isFa, "mx-auto max-w-3xl")} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("cardTitle")}</CardTitle>
          <CardDescription>{tp("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="b_int">{tp("intervalMinutes")}</Label>
            <Input
              id="b_int"
              type="number"
              min={5}
              value={form.backup_interval_minutes}
              onChange={(e) => setForm((f) => ({ ...f, backup_interval_minutes: e.target.value }))}
            />
            <p className="text-xs text-muted-foreground">{tp("intervalHint", { min: formatNumber(5, isFa) })}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="b_tg">{tp("telegramChatId")}</Label>
            <Input
              id="b_tg"
              type="number"
              value={form.backup_telegram_chat_id}
              onChange={(e) => setForm((f) => ({ ...f, backup_telegram_chat_id: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="b_bl">{tp("baleChatId")}</Label>
            <Input
              id="b_bl"
              type="number"
              value={form.backup_bale_chat_id}
              onChange={(e) => setForm((f) => ({ ...f, backup_bale_chat_id: e.target.value }))}
            />
          </div>
          <div className="space-y-3 border-t border-border pt-3">
            {chk("backup_send_telegram_admins", "sendTelegramAdmins")}
            {chk("backup_send_bale_admins", "sendBaleAdmins")}
            {chk("backup_send_telegram_channel", "sendTelegramChannel")}
            {chk("backup_send_bale_channel", "sendBaleChannel")}
          </div>
          <div className="space-y-3 border-t border-border pt-3">
            <p className="text-sm font-medium">{tp("siteStorageTitle")}</p>
            {chk("backup_store_on_site", "storeOnSite")}
            <div className="space-y-2">
              <Label htmlFor="b_ret">{tp("retentionCount")}</Label>
              <Input
                id="b_ret"
                type="number"
                min={1}
                max={500}
                value={form.backup_site_retention_count}
                onChange={(e) => setForm((f) => ({ ...f, backup_site_retention_count: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("retentionHint")}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="b_maxmb">{tp("maxZipMb")}</Label>
              <Input
                id="b_maxmb"
                type="number"
                min={0}
                value={form.backup_max_zip_mb}
                onChange={(e) => setForm((f) => ({ ...f, backup_max_zip_mb: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("maxZipMbHint")}</p>
            </div>
          </div>
          {error ? (
            <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {error}
            </div>
          ) : null}
          <Button type="button" disabled={saving} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle className="text-base">{tp("storedTitle")}</CardTitle>
            <CardDescription>{tp("storedDesc")}</CardDescription>
          </div>
          <Button type="button" variant="secondary" disabled={backupRunning} onClick={() => void onBackupNow()}>
            {backupRunning ? tp("backupNowRunning") : tp("backupNow")}
          </Button>
        </CardHeader>
        <CardContent className="space-y-3">
          {lastBuiltAt > 0 ? (
            <p className="text-xs text-muted-foreground">{tp("lastBuiltAt", { at: tsLabel(lastBuiltAt, isFa) })}</p>
          ) : null}
          {lastBackupAt > 0 ? (
            <p className="text-xs text-muted-foreground">{tp("lastBackupAt", { at: tsLabel(lastBackupAt, isFa) })}</p>
          ) : null}
          {backupMsg ? <p className="text-sm text-muted-foreground">{backupMsg}</p> : null}
          {!storeOnSiteLive ? (
            <p className="text-sm text-amber-700 dark:text-amber-400">{tp("storeOffHint")}</p>
          ) : null}
          {listError ? (
            <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {listError}
            </div>
          ) : null}
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{tp("colDate")}</TableHead>
                  <TableHead>{tp("colSize")}</TableHead>
                  <TableHead>{tp("colPanel")}</TableHead>
                  <TableHead className="w-[100px]" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {listLoading && backupRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="text-center text-muted-foreground">
                      {tp("loading")}
                    </TableCell>
                  </TableRow>
                ) : null}
                {!listLoading && backupRows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="text-center text-muted-foreground">
                      {tp("emptyList")}
                    </TableCell>
                  </TableRow>
                ) : null}
                {backupRows.map((row) => (
                  <TableRow key={row.filename}>
                    <TableCell className="whitespace-nowrap text-xs" dir="ltr">
                      {row.created_at ? formatDateTime(row.created_at, isFa) : "—"}
                    </TableCell>
                    <TableCell className="text-xs">{formatBytes(row.size_bytes, isFa)}</TableCell>
                    <TableCell className="max-w-[200px] text-xs">{panelDbListLabel(row, tp)}</TableCell>
                    <TableCell>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          setRestoreTarget(row)
                          setRestorePanelDb(false)
                        }}
                      >
                        {tp("restoreBtn")}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card className="border-destructive/40">
        <CardHeader>
          <CardTitle className="text-base">{tp("rebuildPanelTitle")}</CardTitle>
          <CardDescription>{tp("rebuildPanelDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="rebuild-panel">{tp("rebuildPanelScope")}</Label>
            <select
              id="rebuild-panel"
              className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={rebuildPanelId}
              onChange={(e) => setRebuildPanelId(e.target.value)}
              disabled={rebuildBusy}
            >
              <option value="0">{tp("rebuildPanelAll")}</option>
              {panelOptions.map((p) => (
                <option key={p.id} value={String(p.id)}>
                  {p.label}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 p-3">
            <p className="text-sm font-medium">{tp("inboundMapTitle")}</p>
            <p className="text-xs text-muted-foreground">{tp("inboundMapDesc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{tp("inboundMapPickPanel")}</p>
            ) : (
              <>
                {inboundMapError ? (
                  <p className="text-xs text-destructive">{inboundMapError}</p>
                ) : null}
                {inboundMapMsg ? <p className="text-xs text-muted-foreground">{inboundMapMsg}</p> : null}
                {inboundMapMissing > 0 ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">
                    {tp("inboundMapMissing", { n: inboundMapMissing })}
                  </p>
                ) : null}
                <div className={cn("flex flex-wrap gap-2")} dir={dashDir(isFa)}>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void loadInboundMap()}
                  >
                    {tp("inboundMapLoad")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy || dbInbounds.length === 0}
                    onClick={applyInboundSuggest}
                  >
                    {tp("inboundMapSuggest")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(false)}
                  >
                    {tp("inboundMapSave")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(true)}
                  >
                    {tp("inboundMapSaveDb")}
                  </Button>
                </div>
                {inboundMapLoading && dbInbounds.length === 0 ? (
                  <p className="text-xs text-muted-foreground">{tp("loading")}</p>
                ) : null}
                {dbInbounds.length > 0 ? (
                  <div className="overflow-x-auto rounded border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="text-xs">{tp("inboundMapDbCol")}</TableHead>
                          <TableHead className="text-xs">{tp("inboundMapPanelCol")}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {dbInbounds.map((row) => {
                          const oldKey = String(row.id)
                          const selected = inboundMapDraft[oldKey] ?? String(row.id)
                          const sameOnPanel = panelInbounds.some((p) => p.id === row.id)
                          return (
                            <TableRow key={oldKey}>
                              <TableCell className="max-w-[280px] text-xs">
                                {inboundRowLabel(row, num(row.service_count))}
                                {row.on_panel_now || sameOnPanel ? (
                                  <span className="mt-1 block text-[10px] text-green-700 dark:text-green-400">
                                    {tp("inboundMapSameId")}
                                  </span>
                                ) : null}
                              </TableCell>
                              <TableCell>
                                <select
                                  className="flex h-8 w-full min-w-[200px] rounded-md border border-input bg-background px-2 text-xs"
                                  value={selected}
                                  disabled={inboundMapLoading || rebuildBusy}
                                  onChange={(e) =>
                                    setInboundMapDraft((d) => ({ ...d, [oldKey]: e.target.value }))
                                  }
                                  aria-label={tp("inboundMapSelect")}
                                >
                                  <option value="">{tp("inboundMapNone")}</option>
                                  {panelInbounds.map((p) => (
                                    <option key={p.id} value={String(p.id)}>
                                      {inboundRowLabel(p)}
                                    </option>
                                  ))}
                                </select>
                              </TableCell>
                            </TableRow>
                          )
                        })}
                      </TableBody>
                    </Table>
                  </div>
                ) : null}
              </>
            )}
          </div>

          <div className="space-y-3 rounded-md border border-border/60 bg-muted/20 p-3">
            <p className="text-sm font-medium">{tp("resellerBackfillTitle")}</p>
            <p className="text-xs text-muted-foreground">{tp("resellerBackfillHint")}</p>
            {resellerBackfillResult ? (
              <p className="whitespace-pre-wrap text-xs text-muted-foreground">{resellerBackfillResult}</p>
            ) : null}
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={resellerBackfillBusy || rebuildBusy}
              onClick={() => {
                void (async () => {
                  setResellerBackfillBusy(true)
                  setResellerBackfillResult(null)
                  setError(null)
                  try {
                    const res = await postAdminMutate("reseller_backfill_run", {})
                    if (!res.ok) {
                      setError(String(res.message || tp("resellerBackfillError")))
                      return
                    }
                    const billing = (res.billing ?? {}) as Record<string, unknown>
                    const invited = (res.invited ?? {}) as Record<string, unknown>
                    setResellerBackfillResult(
                      tp("resellerBackfillResult", {
                        billingUpdated: String(billing.updated ?? 0),
                        billingScanned: String(billing.scanned ?? 0),
                        billingLast: String(billing.last_id ?? 0),
                        invitedUpdated: String(invited.updated ?? 0),
                        invitedScanned: String(invited.scanned ?? 0),
                        invitedLast: String(invited.last_id ?? 0),
                      })
                    )
                  } finally {
                    setResellerBackfillBusy(false)
                  }
                })()
              }}
            >
              {resellerBackfillBusy ? t("loading") : tp("resellerBackfillRun")}
            </Button>
          </div>

          <div className="space-y-3 rounded-md border border-destructive/30 bg-destructive/5 p-3">
            <p className="text-sm font-medium">{tp("fix51200Title")}</p>
            <p className="text-xs text-muted-foreground">{tp("fix51200Desc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{tp("inboundMapPickPanel")}</p>
            ) : fix51200Count != null ? (
              <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                {tp("fix51200Preview", { n: formatNumber(fix51200Count, isFa) })}
              </p>
            ) : null}
            {fix51200Msg ? <p className="whitespace-pre-wrap text-xs text-muted-foreground">{fix51200Msg}</p> : null}
            <Button
              type="button"
              variant="secondary"
              disabled={fix51200Busy || rebuildBusy || num(rebuildPanelId) < 1 || fix51200Count === 0}
              onClick={() => setFix51200Open(true)}
            >
              {fix51200Busy ? tp("fix51200Running") : tp("fix51200Run")}
            </Button>
          </div>

          <label className={cn("flex items-center gap-2 text-sm")} dir={dashDir(isFa)}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={rebuildDryRun}
              onChange={(e) => setRebuildDryRun(e.target.checked)}
              disabled={rebuildBusy}
            />
            {tp("rebuildDryRun")}
          </label>
          {rebuildBusy && rebuildProgress.total > 0 ? (
            <p className="text-sm text-muted-foreground">
              {tp("rebuildProgress", {
                done: formatNumber(rebuildProgress.done, isFa),
                total: formatNumber(rebuildProgress.total, isFa),
              })}
            </p>
          ) : null}
          {rebuildMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{rebuildMsg}</p> : null}
          <Button
            type="button"
            variant="destructive"
            disabled={rebuildBusy}
            onClick={() => setRebuildOpen(true)}
          >
            {rebuildBusy ? tp("rebuildRunning") : tp("rebuildRun")}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("uploadTitle")}</CardTitle>
          <CardDescription>{tp("uploadDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="restore_zip">{tp("uploadPickFile")}</Label>
            <Input
              id="restore_zip"
              type="file"
              accept=".zip,application/zip"
              onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)}
            />
          </div>
          <label className={cn("flex items-center gap-2 text-sm")} dir={dashDir(isFa)}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={uploadConfirm}
              onChange={(e) => setUploadConfirm(e.target.checked)}
            />
            {tp("uploadConfirmLabel")}
          </label>
          <label className={cn("flex items-start gap-2 text-sm")} dir={dashDir(isFa)}>
            <input
              type="checkbox"
              className="mt-0.5 size-4 rounded border-input"
              checked={uploadRestorePanelDb}
              onChange={(e) => setUploadRestorePanelDb(e.target.checked)}
              disabled={uploadBusy}
            />
            <span>
              {tp("restorePanelDbLabel")}
              <span className="mt-1 block text-xs text-muted-foreground">{tp("restorePanelDbHint")}</span>
            </span>
          </label>
          {uploadMsg ? <p className="text-sm text-muted-foreground">{uploadMsg}</p> : null}
          <Button
            type="button"
            variant="destructive"
            disabled={uploadBusy || !uploadFile || !uploadConfirm}
            onClick={() => void onUploadRestore()}
          >
            {tp("uploadRestore")}
          </Button>
        </CardContent>
      </Card>

      <AlertDialog open={fix51200Open} onOpenChange={(open) => !open && !fix51200Busy && setFix51200Open(open)}>
        <AlertDialogContent dir={dashDir(isFa)}>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("fix51200ConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{tp("fix51200ConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")} dir={dashDir(isFa)}>
            <AlertDialogCancel disabled={fix51200Busy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={fix51200Busy} onClick={() => void onFix51200()}>
              {tp("fix51200Confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={rebuildOpen} onOpenChange={(open) => !open && !rebuildBusy && setRebuildOpen(open)}>
        <AlertDialogContent dir={dashDir(isFa)}>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("rebuildConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{tp("rebuildConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")} dir={dashDir(isFa)}>
            <AlertDialogCancel disabled={rebuildBusy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={rebuildBusy} onClick={() => void onRebuildPanels()}>
              {tp("rebuildConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog
        open={restoreTarget != null}
        onOpenChange={(open) => {
          if (!open) {
            setRestoreTarget(null)
            setRestorePanelDb(false)
          }
        }}
      >
        <AlertDialogContent dir={dashDir(isFa)}>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("restoreDialogTitle")}</AlertDialogTitle>
            <AlertDialogDescription className="space-y-3">
              <span className="block">{tp("restoreWarning")}</span>
              {restoreTarget?.has_panel_db ? (
                <label className={cn("flex items-start gap-2 text-sm")} dir={dashDir(isFa)}>
                  <input
                    type="checkbox"
                    className="mt-0.5 size-4 rounded border-input"
                    checked={restorePanelDb}
                    onChange={(e) => setRestorePanelDb(e.target.checked)}
                    disabled={restoreBusy}
                  />
                  <span>
                    {tp("restorePanelDbLabel")}
                    <span className="mt-1 block text-xs opacity-90">{tp("restorePanelDbHint")}</span>
                  </span>
                </label>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")} dir={dashDir(isFa)}>
            <AlertDialogCancel disabled={restoreBusy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={restoreBusy} onClick={() => void onRestoreFile()}>
              {tp("restoreConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
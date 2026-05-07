"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { QRCodeSVG } from "qrcode.react"
import {
  ChevronDown,
  Copy,
  Info,
  Network,
  Pencil,
  QrCode,
  RotateCcw,
  Trash2,
  UserCheck,
  UserPlus,
  UserRound,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { getAdminJson, postAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { dashContentClass, dashFlexRowClass } from "@/lib/dash-locale"
import { gregorianToJalali, jalaliToGregorian } from "@/lib/jalali"
import { formatBytes, formatDateTime, formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

const CONFIGS_BATCH_MAX = 40
const ALL_PANELS = "all" as const
type PanelScope = typeof ALL_PANELS | number

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

/** Stable row id for bulk + busy (includes panel for all-panels mode). */
function rowKey(panelId: number, inboundId: number, email: string): string {
  return `${panelId}::${inboundId}::${email}`
}

function msToDatetimeLocalValue(ms: number): string {
  if (!ms || ms < 1) return ""
  const d = new Date(ms)
  const p = (n: number) => String(n).padStart(2, "0")
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`
}

function datetimeLocalToMs(value: string): number {
  const t = Date.parse(value)
  return Number.isFinite(t) ? t : 0
}

function userRowLabel(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const nm = `${fn} ${ln}`.trim()
  const un = String(u.username ?? "").trim()
  const bits: string[] = []
  if (nm) bits.push(nm)
  if (un) bits.push(`@${un}`)
  bits.push(`#${num(u.id)}`)
  return bits.join(" · ")
}

type ClientRow = DashRecord & {
  panel_id?: number
  email?: string
  enable?: number
  is_online?: number
  is_linked?: number
  used_bytes?: number
  limit_bytes?: number
  total_gb?: number
  expiry_ms?: number
  limit_ip?: number
  first_usage?: number
  linked_service_id?: number
  subscription_url?: string
  primary_config_uri?: string
  comment?: string
  remark?: string
  service_expires_at?: string
  client_ips?: unknown
}

function parseClientIps(row: ClientRow): string[] {
  const raw = row.client_ips
  if (!raw) return []
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x).trim()).filter(Boolean)
  }
  return []
}

function serviceExpiresMs(row: ClientRow): number {
  const s = row.service_expires_at
  if (!s || !String(s).trim()) return 0
  const t = Date.parse(String(s).replace(" ", "T"))
  return Number.isFinite(t) ? t : 0
}

/** Prefer panel `expiry_ms` (synced from panel); fall back to DB service expiry. */
function unifiedExpiryMs(row: ClientRow): number {
  const panel = num(row.expiry_ms)
  if (panel > 0) return panel
  return serviceExpiresMs(row)
}

function expirySourcesDiffer(row: ClientRow): boolean {
  const panel = num(row.expiry_ms)
  const svc = serviceExpiresMs(row)
  if (panel < 1 || svc < 1) return false
  return Math.abs(panel - svc) > 3600000
}

function jalaliMonthMaxDay(jy: number, jm: number): number {
  if (jm <= 6) return 31
  if (jm <= 11) return 30
  const g = jalaliToGregorian(jy, 12, 30)
  const back = gregorianToJalali(g[0], g[1], g[2])
  return back[0] === jy && back[1] === 12 && back[2] === 30 ? 30 : 29
}

function configDisplayName(row: ClientRow): string {
  const em = String(row.email ?? "").trim()
  const rm = String(row.remark ?? "").trim()
  return rm || em
}

function partsFromMs(ms: number): { jy: number; jm: number; jd: number; hh: number; mm: number } {
  const d = new Date(ms)
  const [jy, jm, jd] = gregorianToJalali(d.getFullYear(), d.getMonth() + 1, d.getDate())
  return { jy, jm, jd, hh: d.getHours(), mm: d.getMinutes() }
}

function msFromJalaliParts(jy: number, jm: number, jd: number, hh: number, mm: number): number {
  const [gy, gm, gd] = jalaliToGregorian(jy, jm, jd)
  return new Date(gy, gm - 1, gd, hh, mm, 0, 0).getTime()
}

function ConfigJalaliExpiryFields({
  valueMs,
  onChangeMs,
  tl,
  selectClass,
}: {
  valueMs: number
  onChangeMs: (ms: number) => void
  tl: (k: string) => string
  selectClass: string
}) {
  const effective = valueMs > 0 ? valueMs : Date.now()
  const { jy, jm, jd, hh, mm } = partsFromMs(effective)
  const maxDay = jalaliMonthMaxDay(jy, jm)
  const centerY = partsFromMs(Date.now()).jy
  const years = useMemo(() => {
    const lo = Math.min(centerY - 25, jy - 10)
    const hi = Math.max(centerY + 25, jy + 10)
    return Array.from({ length: hi - lo + 1 }, (_, i) => lo + i)
  }, [centerY, jy])
  const apply = (next: { jy?: number; jm?: number; jd?: number; hh?: number; mm?: number }) => {
    const y = next.jy ?? jy
    const m = next.jm ?? jm
    const maxd = jalaliMonthMaxDay(y, m)
    const d = Math.min(Math.max(1, next.jd ?? jd), maxd)
    const h = Math.min(23, Math.max(0, next.hh ?? hh))
    const mi = Math.min(59, Math.max(0, next.mm ?? mm))
    onChangeMs(msFromJalaliParts(y, m, d, h, mi))
  }
  return (
    <div className="grid gap-2 sm:grid-cols-5">
      <div className="grid gap-1">
        <Label className="text-xs">{tl("jalaliYear")}</Label>
        <select className={selectClass} value={jy} onChange={(e) => apply({ jy: parseInt(e.target.value, 10) })}>
          {years.map((y) => (
            <option key={y} value={y}>
              {y}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-1">
        <Label className="text-xs">{tl("jalaliMonth")}</Label>
        <select className={selectClass} value={jm} onChange={(e) => apply({ jm: parseInt(e.target.value, 10) })}>
          {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
            <option key={m} value={m}>
              {m}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-1">
        <Label className="text-xs">{tl("jalaliDay")}</Label>
        <select className={selectClass} value={jd} onChange={(e) => apply({ jd: parseInt(e.target.value, 10) })}>
          {Array.from({ length: maxDay }, (_, i) => i + 1).map((day) => (
            <option key={day} value={day}>
              {day}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-1">
        <Label className="text-xs">{tl("jalaliHour")}</Label>
        <select className={selectClass} value={hh} onChange={(e) => apply({ hh: parseInt(e.target.value, 10) })}>
          {Array.from({ length: 24 }, (_, i) => (
            <option key={i} value={i}>
              {String(i).padStart(2, "0")}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-1">
        <Label className="text-xs">{tl("jalaliMinute")}</Label>
        <select className={selectClass} value={mm} onChange={(e) => apply({ mm: parseInt(e.target.value, 10) })}>
          {Array.from({ length: 60 }, (_, i) => (
            <option key={i} value={i}>
              {String(i).padStart(2, "0")}
            </option>
          ))}
        </select>
      </div>
    </div>
  )
}

type PlanGroup = {
  plan: DashRecord
  inbound_id: number
  inbound_remark?: string
  protocol?: string
  port?: number
  clients: ClientRow[]
}

type UserPick = { id: number; label: string }

type SnapshotPanelBlock = {
  panel_id: number
  panel_label: string
  plans: PlanGroup[]
  truncated: number
  expired_linked_batch_count: number
  cache_synced_at: string | null
  cache_stale: boolean
  needs_sync: boolean
}

type MergedSnapshot = {
  panels: SnapshotPanelBlock[]
  default_svp_user_id: number
  syncWarnings: string[]
}

async function copyToClipboard(text: string): Promise<boolean> {
  const t = text.trim()
  if (!t) return false
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(t)
      return true
    }
  } catch {
    /* fallthrough */
  }
  try {
    const ta = document.createElement("textarea")
    ta.value = t
    ta.style.position = "fixed"
    ta.style.left = "-9999px"
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    const ok = document.execCommand("copy")
    document.body.removeChild(ta)
    return ok
  } catch {
    return false
  }
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div
      className={cn(
        "grid gap-0.5 border-b border-border/40 py-2 last:border-0 sm:grid-cols-[minmax(7rem,32%)_1fr] sm:gap-3"
      )}
    >
      <div className="text-xs font-medium text-muted-foreground">{label}</div>
      <div className="min-w-0 text-sm break-all">{children}</div>
    </div>
  )
}

export function DashboardConfigsAdmin({
  panels,
  isFa,
  configsActive = true,
  onMutateSuccess,
}: {
  panels: DashRecord[]
  isFa: boolean
  configsActive?: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tl = useCallback(
    (k: string, opts?: Record<string, string | number>) => t(`configsAdmin.${k}`, opts),
    [t]
  )

  const [panelScope, setPanelScope] = useState<PanelScope>(ALL_PANELS)
  const [merged, setMerged] = useState<MergedSnapshot | null>(null)
  const [refreshing, setRefreshing] = useState(false)
  const refreshGen = useRef(0)

  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const [infoOpen, setInfoOpen] = useState(false)
  const [infoRow, setInfoRow] = useState<ClientRow | null>(null)

  const [qrOpen, setQrOpen] = useState(false)
  const [qrRow, setQrRow] = useState<ClientRow | null>(null)
  const [qrCopyHint, setQrCopyHint] = useState<string | null>(null)
  const qrCopyTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const [editOpen, setEditOpen] = useState(false)
  const [editPanelId, setEditPanelId] = useState(0)
  const [editInboundId, setEditInboundId] = useState(0)
  const [editEmail, setEditEmail] = useState("")
  const [editRemark, setEditRemark] = useState("")
  const [editClientComment, setEditClientComment] = useState("")
  const [editLimitIp, setEditLimitIp] = useState("0")
  const [editStartAfterFirstUse, setEditStartAfterFirstUse] = useState(false)
  const [editTotalGb, setEditTotalGb] = useState("0")
  /** Panel expiry in local Date ms; 0 = clear / unlimited per save rules */
  const [editExpiryMs, setEditExpiryMs] = useState(0)

  const [delOpen, setDelOpen] = useState(false)
  const [delRow, setDelRow] = useState<ClientRow | null>(null)
  const [delInboundId, setDelInboundId] = useState(0)
  const [delPanelId, setDelPanelId] = useState(0)

  const [resetOpen, setResetOpen] = useState(false)
  const [resetRow, setResetRow] = useState<ClientRow | null>(null)
  const [resetInboundId, setResetInboundId] = useState(0)
  const [resetPanelId, setResetPanelId] = useState(0)

  const [quickOpen, setQuickOpen] = useState(false)
  const [quickPlanId, setQuickPlanId] = useState(0)
  const [quickTarget, setQuickTarget] = useState("")

  const [expConfirm, setExpConfirm] = useState("")
  const [expDeleteAck, setExpDeleteAck] = useState(false)
  const [expBusy, setExpBusy] = useState(false)

  const [bulkSel, setBulkSel] = useState<Record<string, { panel_id: number; inbound_id: number; email: string }>>({})
  const [ipsTarget, setIpsTarget] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [batchBusy, setBatchBusy] = useState(false)

  const [linkOpen, setLinkOpen] = useState(false)
  const [linkCtx, setLinkCtx] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [linkQuery, setLinkQuery] = useState("")
  const [linkHits, setLinkHits] = useState<DashRecord[]>([])
  const [linkPick, setLinkPick] = useState<UserPick | null>(null)
  const linkSearchTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const [busyRow, setBusyRow] = useState<string | null>(null)

  const bulkCount = useMemo(() => Object.keys(bulkSel).length, [bulkSel])
  const singlePanelMode = typeof panelScope === "number"

  const panelOptions = useMemo(() => {
    return panels.map((p) => ({
      id: num(p.id),
      label: String(p.label ?? "").trim() || `#${num(p.id)}`,
    }))
  }, [panels])

  const panelLabel = useCallback(
    (id: number) => panelOptions.find((p) => p.id === id)?.label ?? `#${id}`,
    [panelOptions]
  )

  const resolvePanelIds = useCallback((): number[] => {
    if (panelScope === ALL_PANELS) {
      return panelOptions.map((p) => p.id).filter((id) => id > 0)
    }
    return typeof panelScope === "number" && panelScope > 0 ? [panelScope] : []
  }, [panelScope, panelOptions])

  const runAutoRefresh = useCallback(async () => {
    const ids = resolvePanelIds()
    if (ids.length < 1) {
      setErr(tl("pickPanel"))
      setMerged(null)
      return
    }
    const gen = ++refreshGen.current
    setRefreshing(true)
    setErr(null)
    setMsg(null)
    const warnings: string[] = []
    try {
      await Promise.all(
        ids.map(async (pid) => {
          const syncJson = await postAdminJson("/dashboard/admin/configs-sync", { panel_id: pid })
          if (!syncJson.ok) {
            warnings.push(`${panelLabel(pid)}: ${String(syncJson.message ?? tl("syncFailed"))}`)
          }
        })
      )
      if (gen !== refreshGen.current) return

      const blocks: SnapshotPanelBlock[] = []
      let defaultUid = 0

      await Promise.all(
        ids.map(async (pid) => {
          const json = await getAdminJson("/dashboard/admin/configs-snapshot", { panel_id: pid })
          if (gen !== refreshGen.current) return
          if (!json.ok) {
            warnings.push(`${panelLabel(pid)}: ${String(json.message ?? tl("loadFailed"))}`)
            return
          }
          const data = json.data as Record<string, unknown> | undefined
          const rawPlans = data && Array.isArray(data.plans) ? (data.plans as PlanGroup[]) : []
          const plans: PlanGroup[] = rawPlans.map((pg) => ({
            ...pg,
            clients: (pg.clients ?? []).map((c) => ({ ...(c as ClientRow), panel_id: pid })),
          }))
          blocks.push({
            panel_id: pid,
            panel_label: panelLabel(pid),
            plans,
            truncated: num(data?.truncated),
            expired_linked_batch_count: num(data?.expired_linked_batch_count),
            cache_synced_at:
              typeof data?.cache_synced_at === "string" && data.cache_synced_at ? data.cache_synced_at : null,
            cache_stale: Boolean(data?.cache_stale),
            needs_sync: Boolean(data?.needs_sync),
          })
          const du = num(data?.default_svp_user_id)
          if (du > 0 && defaultUid < 1) defaultUid = du
        })
      )

      if (gen !== refreshGen.current) return
      blocks.sort((a, b) => a.panel_id - b.panel_id)
      setMerged({ panels: blocks, default_svp_user_id: defaultUid, syncWarnings: warnings })
      setExpConfirm("")
      setExpDeleteAck(false)
      setBulkSel({})
      if (warnings.length) {
        setMsg(tl("partialSyncNotice"))
      } else {
        setMsg(tl("autoRefreshed"))
      }
    } finally {
      if (gen === refreshGen.current) setRefreshing(false)
    }
  }, [resolvePanelIds, panelLabel, tl])

  const panelIdsKey = useMemo(() => panelOptions.map((p) => p.id).sort((a, b) => a - b).join(","), [panelOptions])

  useEffect(() => {
    if (!configsActive) return
    void runAutoRefresh()
  }, [configsActive, panelScope, panelIdsKey, runAutoRefresh])

  useEffect(() => {
    if (!configsActive) return
    const onVis = () => {
      if (document.visibilityState === "visible") void runAutoRefresh()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => document.removeEventListener("visibilitychange", onVis)
  }, [configsActive, runAutoRefresh])

  useEffect(() => {
    return () => {
      if (linkSearchTimer.current) clearTimeout(linkSearchTimer.current)
      if (qrCopyTimer.current) clearTimeout(qrCopyTimer.current)
    }
  }, [])

  useEffect(() => {
    if (linkOpen && linkCtx) {
      setLinkQuery("")
      setLinkHits([])
      setLinkPick(null)
    }
  }, [linkOpen, linkCtx?.panel_id, linkCtx?.inbound_id, linkCtx?.row.email])

  const scheduleLinkSearch = useCallback((q: string) => {
    if (linkSearchTimer.current) clearTimeout(linkSearchTimer.current)
    const trimmed = q.trim()
    if (trimmed.length < 2) {
      setLinkHits([])
      return
    }
    linkSearchTimer.current = setTimeout(() => {
      void (async () => {
        try {
          const json = await getAdminJson("/dashboard/admin/user-search", { q: trimmed })
          if (!json.ok) return
          const users = Array.isArray(json.users) ? (json.users as DashRecord[]) : []
          setLinkHits(users)
        } catch {
          /* ignore */
        }
      })()
    }, 320)
  }, [])

  const afterMutate = useCallback(async () => {
    onMutateSuccess?.()
    await runAutoRefresh()
  }, [onMutateSuccess, runAutoRefresh])

  const onToggleEnable = useCallback(
    async (panel_id: number, inboundId: number, row: ClientRow, enabled: boolean) => {
      const email = String(row.email ?? "")
      const rk = rowKey(panel_id, inboundId, email)
      setErr(null)
      setBusyRow(rk)
      try {
        const res = await postAdminMutate("configs_client_toggle_enable", {
          panel_id,
          inbound_id: inboundId,
          email,
          enable: enabled ? 1 : 0,
        })
        if (!res.ok) {
          setErr(res.message ?? tl("mutateError"))
          return
        }
        await afterMutate()
      } finally {
        setBusyRow(null)
      }
    },
    [afterMutate, tl]
  )

  const onResetTraffic = useCallback(async () => {
    if (!resetRow || resetInboundId < 1 || resetPanelId < 1) return
    const email = String(resetRow.email ?? "")
    const rk = rowKey(resetPanelId, resetInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_reset_traffic", {
        panel_id: resetPanelId,
        inbound_id: resetInboundId,
        email,
      })
      if (!res.ok) {
        setErr(res.message ?? tl("mutateError"))
        return
      }
      setResetOpen(false)
      setResetRow(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, resetInboundId, resetPanelId, resetRow, tl])

  const onDelete = useCallback(async () => {
    if (!delRow || delInboundId < 1 || delPanelId < 1) return
    const email = String(delRow.email ?? "")
    const rk = rowKey(delPanelId, delInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_delete", {
        panel_id: delPanelId,
        inbound_id: delInboundId,
        email,
        linked_service_id: num(delRow.linked_service_id),
      })
      if (!res.ok) {
        setErr(res.message ?? res.reason ?? tl("mutateError"))
        return
      }
      setDelOpen(false)
      setDelRow(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, delInboundId, delPanelId, delRow, tl])

  const onSaveEdit = useCallback(async () => {
    if (editInboundId < 1 || !editEmail || editPanelId < 1) return
    setErr(null)
    setBusyRow(rowKey(editPanelId, editInboundId, editEmail))
    try {
      const payload: Record<string, unknown> = {
        panel_id: editPanelId,
        inbound_id: editInboundId,
        email: editEmail,
        client_remark: editRemark,
        client_comment: editClientComment,
        limit_ip: parseInt(editLimitIp, 10) || 0,
        start_after_first_use: editStartAfterFirstUse ? 1 : 0,
        total_gb: parseInt(editTotalGb, 10) || 0,
      }
      if (editExpiryMs > 0) {
        payload.expiry_ms = editExpiryMs
      } else {
        payload.expiry_ms = 0
      }
      const res = await postAdminMutate("configs_panel_client_patch", payload)
      if (!res.ok) {
        setErr(res.message ?? tl("mutateError"))
        return
      }
      setEditOpen(false)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [
    afterMutate,
    editClientComment,
    editEmail,
    editExpiryMs,
    editInboundId,
    editLimitIp,
    editPanelId,
    editRemark,
    editStartAfterFirstUse,
    editTotalGb,
    tl,
  ])

  const submitLink = useCallback(async () => {
    if (!linkCtx) return
    const { panel_id, inbound_id, row } = linkCtx
    const email = String(row.email ?? "")
    const rk = rowKey(panel_id, inbound_id, email)
    const body: Record<string, unknown> = {
      inbound_id,
      panel_id,
      email,
    }
    if (linkPick && linkPick.id > 0) {
      body.user_id = linkPick.id
    } else if (linkQuery.trim().length >= 2) {
      body.user_query = linkQuery.trim()
    } else {
      const uid = parseInt(linkQuery.trim(), 10)
      if (Number.isFinite(uid) && uid >= 1) {
        body.user_id = uid
      } else {
        setErr(t("inboundLinkAdmin.badLinkParams"))
        return
      }
    }
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("inbound_link", body)
      if (!res.ok) {
        const rsn = res.reason
        if (rsn === "ambiguous") setErr(t("inboundLinkAdmin.resolveAmbiguous"))
        else if (rsn === "not_found" || rsn === "empty") setErr(t("inboundLinkAdmin.resolveNotFound"))
        else setErr(res.message ?? "error")
        return
      }
      setLinkOpen(false)
      setLinkCtx(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, linkCtx, linkPick, linkQuery, t])

  const runQuickAdd = useCallback(async () => {
    const def = merged?.default_svp_user_id ?? 0
    const raw = quickTarget.trim()
    let target = def
    if (raw) {
      const n = parseInt(raw, 10)
      if (Number.isFinite(n) && n >= 1) target = n
      else {
        setErr(t("inboundLinkAdmin.badLinkParams"))
        return
      }
    }
    if (target < 1 || quickPlanId < 1) {
      setErr(t("inboundLinkAdmin.badLinkParams"))
      return
    }
    setErr(null)
    setBusyRow(`quick:${quickPlanId}`)
    try {
      const res = await postAdminMutate("user_create_service", {
        target_user_id: target,
        plan_id: quickPlanId,
        mode: "free",
      })
      if (!res.ok) {
        setErr(res.reason ?? res.message ?? tl("mutateError"))
        return
      }
      setQuickOpen(false)
      setQuickPlanId(0)
      setQuickTarget("")
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, merged?.default_svp_user_id, quickPlanId, quickTarget, t, tl])

  const runDeleteExpired = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    const block = merged?.panels.find((p) => p.panel_id === panelScope)
    const n = block?.expired_linked_batch_count ?? 0
    if (n < 1) return
    if (!expDeleteAck) {
      setErr(tl("deleteExpiredAckError"))
      return
    }
    const typed = parseInt(expConfirm.trim(), 10)
    if (typed !== n) {
      setErr(tl("confirmMismatch"))
      return
    }
    setExpBusy(true)
    setErr(null)
    try {
      const res = await postAdminMutate("configs_delete_expired_linked", {
        panel_id: panelScope,
        confirm_count: n,
      })
      if (!res.ok) {
        if (res.message === "confirm_mismatch") {
          const exp = (res.data as { expected_count?: number } | undefined)?.expected_count
          setErr(tl("confirmMismatch") + (exp != null ? ` (${exp})` : ""))
        } else {
          setErr(res.message ?? tl("mutateError"))
        }
        return
      }
      setExpConfirm("")
      setExpDeleteAck(false)
      await afterMutate()
    } finally {
      setExpBusy(false)
    }
  }, [afterMutate, expConfirm, expDeleteAck, merged?.panels, panelScope, singlePanelMode, tl])

  const toggleBulkRow = useCallback(
    (rk: string, panel_id: number, inboundId: number, email: string, checked: boolean) => {
      if (!checked) {
        setBulkSel((prev) => {
          const next = { ...prev }
          delete next[rk]
          return next
        })
        setErr(null)
        return
      }
      setBulkSel((prev) => {
        if (prev[rk]) return prev
        if (Object.keys(prev).length >= CONFIGS_BATCH_MAX) {
          queueMicrotask(() => setErr(tl("batchMax", { max: CONFIGS_BATCH_MAX })))
          return prev
        }
        queueMicrotask(() => setErr(null))
        return { ...prev, [rk]: { panel_id, inbound_id: inboundId, email } }
      })
    },
    [tl]
  )

  const runClientsBatch = useCallback(
    async (batch_op: "reset_traffic" | "set_enable", enable?: boolean) => {
      if (!singlePanelMode || typeof panelScope !== "number") return
      const items = Object.values(bulkSel)
      if (items.length < 1) return
      if (items.some((it) => it.panel_id !== panelScope)) {
        setErr(tl("batchSinglePanelOnly"))
        return
      }
      if (items.length > CONFIGS_BATCH_MAX) {
        setErr(tl("batchMax", { max: CONFIGS_BATCH_MAX }))
        return
      }
      setBatchBusy(true)
      setErr(null)
      try {
        const payloadItems = items.map((it) =>
          batch_op === "set_enable" ? { ...it, enable: enable ? 1 : 0 } : { inbound_id: it.inbound_id, email: it.email }
        )
        const res = await postAdminMutate("configs_clients_batch", {
          panel_id: panelScope,
          batch_op,
          items: payloadItems,
        })
        if (!res.ok && res.message !== "partial") {
          setErr(res.message ?? tl("mutateError"))
          return
        }
        if (res.message === "partial") {
          const d = res.data as { succeeded?: number; failed?: unknown[] } | undefined
          const okn = num(d?.succeeded)
          const fn = Array.isArray(d?.failed) ? d.failed.length : 0
          setMsg(tl("batchPartial", { ok: okn, fail: fn }))
        } else {
          setMsg(tl("autoRefreshed"))
        }
        setBulkSel({})
        await afterMutate()
      } finally {
        setBatchBusy(false)
      }
    },
    [afterMutate, bulkSel, panelScope, singlePanelMode, tl]
  )

  const openEdit = (panel_id: number, inboundId: number, row: ClientRow) => {
    const email = String(row.email ?? "")
    setEditPanelId(panel_id)
    setEditInboundId(inboundId)
    setEditEmail(email)
    setEditRemark(String(row.remark ?? ""))
    setEditClientComment(String(row.comment ?? ""))
    setEditLimitIp(String(num(row.limit_ip)))
    setEditStartAfterFirstUse(num(row.first_usage) !== 0)
    setEditTotalGb(String(num(row.total_gb)))
    setEditExpiryMs(unifiedExpiryMs(row))
    setEditOpen(true)
  }

  const openLinkModal = (panel_id: number, inboundId: number, row: ClientRow) => {
    setLinkCtx({ panel_id, inbound_id: inboundId, row })
    setLinkOpen(true)
  }

  const progressVal = (row: ClientRow) => {
    const lim = num(row.limit_bytes)
    const used = num(row.used_bytes)
    if (lim <= 0) return 0
    return Math.min(100, Math.round((100 * used) / lim))
  }

  const unifiedExpirySummary = useCallback(
    (row: ClientRow) => {
      const ms = unifiedExpiryMs(row)
      if (ms < 1) return tl("noPanelExpiry")
      const left = ms - Date.now()
      const abs = formatDateTime(ms, isFa)
      if (left <= 0) return `${tl("expired")} — ${abs}`
      const d = Math.ceil(left / 86400000)
      return `${abs} · ${tl("daysLeft", { n: d })}`
    },
    [isFa, tl]
  )

  const clientStats = useMemo(() => {
    if (!merged) return null
    let total = 0
    let enabled = 0
    let online = 0
    let linked = 0
    let expired = 0
    const now = Date.now()
    for (const block of merged.panels) {
      for (const pg of block.plans) {
        for (const row of pg.clients) {
          total++
          if (num(row.enable) !== 0) enabled++
          if (num(row.is_online) === 1) online++
          if (num(row.is_linked) !== 0) linked++
          const ms = unifiedExpiryMs(row)
          if (ms > 0 && ms <= now) expired++
        }
      }
    }
    return {
      total,
      enabled,
      disabled: total - enabled,
      online,
      linked,
      unlinked: total - linked,
      expired,
    }
  }, [merged])

  const showQrCopy = (kind: "ok" | "fail") => {
    if (qrCopyTimer.current) clearTimeout(qrCopyTimer.current)
    setQrCopyHint(kind === "ok" ? tl("copyOk") : `__err__${tl("copyFail")}`)
    qrCopyTimer.current = setTimeout(() => setQrCopyHint(null), 2200)
  }

  const totalTruncated = merged?.panels.reduce((s, p) => s + p.truncated, 0) ?? 0
  const expiredBlock =
    singlePanelMode && typeof panelScope === "number"
      ? merged?.panels.find((p) => p.panel_id === panelScope)
      : null
  const anyStale = merged?.panels.some((p) => p.cache_stale)
  const anyNeeds = merged?.panels.some((p) => p.needs_sync)

  const contentClass = dashContentClass(isFa)
  const flexRow = dashFlexRowClass(isFa)
  const dialogDir = isFa ? ("rtl" as const) : ("ltr" as const)
  const dialogHeaderClass = cn(
    "flex flex-col gap-2",
    isFa ? "text-right sm:text-right" : "text-center sm:text-left"
  )
  const dialogContentCn = (extra: string) => cn(extra, contentClass, isFa && "text-right")
  const selectClass = cn(
    "flex h-9 w-full rounded-md border border-input bg-background px-2 py-1 text-sm shadow-sm",
    isFa && "text-right"
  )

  return (
    <div className={cn("space-y-6", contentClass)}>
      <div>
        <h2 className="text-lg font-medium">{tl("title")}</h2>
        <p className="text-sm text-muted-foreground">{tl("subtitle")}</p>
        <p className="mt-1 text-xs text-muted-foreground">{tl("autoSyncHint")}</p>
      </div>

      <div className="flex flex-col gap-4 rounded-lg border border-border/60 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <div className="grid gap-2">
          <Label>{tl("fieldPanel")}</Label>
          <select
            className={cn(
              "flex h-9 w-full max-w-md rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm",
              isFa && "text-right"
            )}
            value={panelScope === ALL_PANELS ? "all" : String(panelScope)}
            onChange={(e) => {
              const v = e.target.value
              if (v === "all") setPanelScope(ALL_PANELS)
              else {
                const n = parseInt(v, 10)
                setPanelScope(Number.isFinite(n) && n > 0 ? n : ALL_PANELS)
              }
              setMerged(null)
              setBulkSel({})
              setErr(null)
              setMsg(null)
            }}
          >
            <option value="all">{tl("allPanels")}</option>
            {panelOptions.length === 0 ? (
              <option value="">{tl("noPanels")}</option>
            ) : (
              panelOptions.map((p) => (
                <option key={p.id} value={p.id}>
                  #{p.id} — {p.label}
                </option>
              ))
            )}
          </select>
        </div>
        <div className="text-sm text-muted-foreground">
          {refreshing ? <span className="text-foreground">{tl("syncBusy")}</span> : <span>{tl("idleReady")}</span>}
        </div>
      </div>

      {merged && clientStats ? (
        <div className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-sm">
          <p>
            {tl("statsLine", {
              total: clientStats.total,
              enabled: clientStats.enabled,
              disabled: clientStats.disabled,
              online: clientStats.online,
              expired: clientStats.expired,
              linked: clientStats.linked,
              unlinked: clientStats.unlinked,
            })}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">{tl("statsHint")}</p>
        </div>
      ) : null}

      {merged && merged.syncWarnings.length > 0 ? (
        <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-100">
          <p className="font-medium">{tl("partialSyncNotice")}</p>
          <ul className="mt-1 list-inside list-disc space-y-0.5">
            {merged.syncWarnings.slice(0, 8).map((w, i) => (
              <li key={i}>{w}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {merged && (merged.panels.some((p) => p.cache_synced_at) || anyStale || anyNeeds) ? (
        <div className="space-y-1 text-xs text-muted-foreground">
          {merged.panels.map((p) =>
            p.cache_synced_at ? (
              <p key={`cs-${p.panel_id}`}>
                #{p.panel_id} — {tl("cacheSyncedAt", { time: p.cache_synced_at })}
              </p>
            ) : null
          )}
          {anyStale ? <p className="text-amber-700 dark:text-amber-400">{tl("cacheStaleBanner")}</p> : null}
          {anyNeeds ? <p>{tl("needsSyncBanner")}</p> : null}
        </div>
      ) : null}

      {merged && totalTruncated > 0 ? (
        <p className="text-xs text-amber-600 dark:text-amber-400">{tl("truncated")}</p>
      ) : null}

      {expiredBlock && expiredBlock.expired_linked_batch_count > 0 ? (
        <div className="space-y-2 rounded-lg border border-destructive/40 bg-destructive/5 p-4">
          <p className="text-sm font-medium">{tl("deleteExpired")}</p>
          <p className="text-xs text-muted-foreground">{tl("deleteExpiredHint")}</p>
          <label className={cn("flex items-start gap-2 text-xs text-muted-foreground", isFa && "flex-row-reverse")}>
            <input
              type="checkbox"
              className="mt-0.5"
              checked={expDeleteAck}
              onChange={(e) => setExpDeleteAck(e.target.checked)}
            />
            <span>{tl("deleteExpiredAck")}</span>
          </label>
          <div className={cn("flex flex-wrap items-end gap-2", flexRow)}>
            <div className="grid gap-1">
              <Label className="text-xs">{tl("confirmCount")}</Label>
              <Input
                className="w-40"
                inputMode="numeric"
                value={expConfirm}
                onChange={(e) => setExpConfirm(e.target.value)}
                placeholder={String(expiredBlock.expired_linked_batch_count)}
              />
            </div>
            <Button type="button" variant="destructive" disabled={expBusy} onClick={() => void runDeleteExpired()}>
              {expBusy ? tl("loading") : tl("runDeleteExpired")}
            </Button>
          </div>
        </div>
      ) : null}

      {msg ? <p className="text-sm text-green-600 dark:text-green-400">{msg}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}

      {singlePanelMode && bulkCount > 0 ? (
        <div
          className={cn(
            "flex flex-wrap items-center gap-3 rounded-lg border border-border/60 bg-muted/30 p-3",
            isFa && "flex-row-reverse"
          )}
        >
          <span className="text-sm font-medium">{tl("batchBar", { n: bulkCount, max: CONFIGS_BATCH_MAX })}</span>
          <div className={cn("flex flex-wrap gap-2", isFa && "flex-row-reverse")}>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={batchBusy}
              onClick={() => {
                setBulkSel({})
                setErr(null)
              }}
            >
              {tl("batchClear")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={batchBusy}
              onClick={() => void runClientsBatch("reset_traffic")}
            >
              {tl("batchReset")}
            </Button>
            <Button type="button" size="sm" disabled={batchBusy} onClick={() => void runClientsBatch("set_enable", true)}>
              {tl("batchEnable")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={batchBusy}
              onClick={() => void runClientsBatch("set_enable", false)}
            >
              {tl("batchDisable")}
            </Button>
          </div>
        </div>
      ) : null}

      {!merged && refreshing ? (
        <p className="text-sm text-muted-foreground">{tl("loading")}</p>
      ) : null}

      {merged && merged.panels.every((p) => p.plans.length === 0) ? (
        <p className="text-sm text-muted-foreground">{tl("noPlans")}</p>
      ) : null}

      {merged?.panels.map((block) => (
        <div key={block.panel_id} className="space-y-3 rounded-xl border border-border/60 bg-card/30 p-3 sm:p-4">
          <div className={cn("flex flex-wrap items-baseline justify-between gap-2 border-b border-border/50 pb-2", flexRow)}>
            <div>
              <h3 className="text-base font-semibold">
                {tl("panelHeading", { id: block.panel_id, label: block.panel_label })}
              </h3>
              {block.truncated > 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">{tl("panelTruncated", { n: block.truncated })}</p>
              ) : null}
            </div>
          </div>

          {block.plans.map((pg) => {
            const plan = pg.plan
            const planName = String(plan.name ?? `#${num(plan.id)}`)
            const planId = num(plan.id)
            const iid = num(pg.inbound_id)
            const sub = tl("planInbound", {
              id: iid,
              protocol: String(pg.protocol ?? "—"),
              port: num(pg.port),
            })
            return (
              <Collapsible
                key={`${block.panel_id}-${planId}-${iid}`}
                defaultOpen
                className="group/collapsible overflow-hidden rounded-lg border border-border/50"
              >
                <div className="flex items-stretch gap-0 border-b border-border/50">
                  <CollapsibleTrigger asChild>
                    <button
                      type="button"
                      className={cn(
                        "flex min-w-0 flex-1 items-center gap-2 p-3 text-start hover:bg-muted/40",
                        isFa && "flex-row-reverse text-right"
                      )}
                    >
                      <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                      <div className="min-w-0">
                        <div className="font-medium">{planName}</div>
                        <div className="text-xs text-muted-foreground">{sub}</div>
                      </div>
                    </button>
                  </CollapsibleTrigger>
                  <div className="flex shrink-0 items-center border-s border-border/50 px-2">
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={busyRow === `quick:${planId}` || planId < 1}
                      onClick={(e) => {
                        e.preventDefault()
                        e.stopPropagation()
                        setQuickPlanId(planId)
                        setQuickTarget(
                          merged.default_svp_user_id > 0 ? String(merged.default_svp_user_id) : ""
                        )
                        setQuickOpen(true)
                      }}
                    >
                      <UserPlus className="size-4" />
                      <span className="sr-only md:not-sr-only md:inline">{tl("quickAdd")}</span>
                    </Button>
                  </div>
                </div>
                <CollapsibleContent>
                  <div className="border-t border-border/50 bg-muted/5">
                    {pg.clients.length === 0 ? (
                      <p className="p-3 text-sm text-muted-foreground">{tl("noClientsInPlan")}</p>
                    ) : (
                      pg.clients.map((row) => {
                        const pid = num(row.panel_id) || block.panel_id
                        const email = String(row.email ?? "")
                        const rk = rowKey(pid, iid, email)
                        const enabled = num(row.enable) !== 0
                        const online = num(row.is_online) === 1
                        const linked = num(row.is_linked) !== 0
                        const capLabel =
                          num(row.total_gb) < 1 && num(row.limit_bytes) < 1
                            ? tl("unlimited")
                            : `${formatNumber(num(row.total_gb), isFa)} ${tl("gbUnit")}`
                        return (
                          <div
                            key={rk}
                            className="border-b border-border/40 px-3 py-3 last:border-b-0 sm:grid sm:grid-cols-[1fr_auto] sm:items-center sm:gap-3"
                          >
                            <div className="min-w-0 space-y-2">
                              <div
                                dir={isFa ? "rtl" : "ltr"}
                                className="flex w-full flex-wrap items-center justify-between gap-2"
                              >
                                <span
                                  className="min-w-0 max-w-[min(100%,20rem)] truncate font-mono text-sm font-medium"
                                  title={configDisplayName(row)}
                                >
                                  {configDisplayName(row)}
                                </span>
                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                  {singlePanelMode ? (
                                    <input
                                      type="checkbox"
                                      className="size-4 shrink-0"
                                      checked={Boolean(bulkSel[rk])}
                                      disabled={batchBusy || busyRow === rk}
                                      onChange={(e) => toggleBulkRow(rk, pid, iid, email, e.target.checked)}
                                      aria-label={tl("batchSelectRow")}
                                    />
                                  ) : null}
                                  <Switch
                                    checked={enabled}
                                    disabled={batchBusy || busyRow === rk}
                                    aria-label={tl("enable")}
                                    onCheckedChange={(v) => void onToggleEnable(pid, iid, row, v)}
                                  />
                                  <Badge variant={online ? "default" : "secondary"}>
                                    {online ? tl("online") : tl("offline")}
                                  </Badge>
                                </div>
                              </div>
                              {num(row.limit_bytes) > 0 ? (
                                <div className="dir-ltr max-w-xl" dir="ltr">
                                  <div className="flex items-center gap-2">
                                    <span className="w-24 shrink-0 text-xs text-muted-foreground tabular-nums sm:w-28">
                                      {formatBytes(num(row.used_bytes), isFa)}
                                    </span>
                                    <Progress value={progressVal(row)} className="h-2 min-w-0 flex-1" />
                                    <span className="w-24 shrink-0 text-end text-xs text-muted-foreground tabular-nums sm:w-28">
                                      {formatBytes(num(row.limit_bytes), isFa)}
                                    </span>
                                  </div>
                                  <p className="mt-1.5 text-center text-xs text-muted-foreground">
                                    {tl("expiryUnified")}: {unifiedExpirySummary(row)}
                                  </p>
                                </div>
                              ) : (
                                <div className="dir-ltr space-y-1 text-xs text-muted-foreground" dir="ltr">
                                  <div className="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                    <span>
                                      {tl("used")}: {formatBytes(num(row.used_bytes), isFa)}
                                    </span>
                                    <span>
                                      {tl("cap")}: {capLabel}
                                    </span>
                                  </div>
                                  <p className="text-center">
                                    {tl("expiryUnified")}: {unifiedExpirySummary(row)}
                                  </p>
                                </div>
                              )}
                            </div>
                            <div className={cn("mt-3 flex flex-wrap items-center gap-1 sm:mt-0", flexRow)}>
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className={cn("size-9", linked ? "text-primary" : "text-muted-foreground")}
                                    disabled={batchBusy || busyRow === rk}
                                    onClick={() => openLinkModal(pid, iid, row)}
                                    aria-label={linked ? tl("linkStatusLinked") : tl("linkStatusUnlinked")}
                                  >
                                    {linked ? <UserCheck className="size-4" /> : <UserRound className="size-4" />}
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                  {linked ? tl("linkUserEdit") : tl("linkUserAdd")}
                                </TooltipContent>
                              </Tooltip>
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-9"
                                    onClick={() => {
                                      setInfoRow(row)
                                      setInfoOpen(true)
                                    }}
                                  >
                                    <Info className="size-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>{tl("infoTitle")}</TooltipContent>
                              </Tooltip>
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-9"
                                    onClick={() => {
                                      setQrRow(row)
                                      setQrCopyHint(null)
                                      setQrOpen(true)
                                    }}
                                  >
                                    <QrCode className="size-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>{tl("qrTitle")}</TooltipContent>
                              </Tooltip>
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-9"
                                    onClick={() => openEdit(pid, iid, row)}
                                  >
                                    <Pencil className="size-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent>{tl("editTitle")}</TooltipContent>
                              </Tooltip>
                              <Tooltip>
                                <TooltipTrigger asChild>
                                  <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-9"
                                    onClick={() => setIpsTarget({ panel_id: pid, inbound_id: iid, row })}
                                  >
                                    <Network className="size-4" />
                                  </Button>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-xs">{tl("ipsPlaceholder")}</TooltipContent>
                              </Tooltip>
                              <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-9"
                                disabled={batchBusy || busyRow === rk}
                                onClick={() => {
                                  setResetPanelId(pid)
                                  setResetInboundId(iid)
                                  setResetRow(row)
                                  setResetOpen(true)
                                }}
                              >
                                <RotateCcw className="size-4" />
                              </Button>
                              <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-9 text-destructive"
                                disabled={batchBusy || busyRow === rk}
                                onClick={() => {
                                  setDelPanelId(pid)
                                  setDelInboundId(iid)
                                  setDelRow(row)
                                  setDelOpen(true)
                                }}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </div>
                          </div>
                        )
                      })
                    )}
                  </div>
                </CollapsibleContent>
              </Collapsible>
            )
          })}
        </div>
      ))}

      <Dialog open={infoOpen} onOpenChange={setInfoOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("infoTitle")}</DialogTitle>
          </DialogHeader>
          {infoRow ? (
            <div className="max-h-[70vh] space-y-4 overflow-y-auto pe-1">
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsIdentity")}
                </p>
                <DetailRow label={tl("fieldEmail")}>{String(infoRow.email ?? "—")}</DetailRow>
                <DetailRow label={tl("fieldRemark")}>{String(infoRow.remark ?? tl("none"))}</DetailRow>
                <DetailRow label={tl("fieldAdminComment")}>{String(infoRow.comment ?? tl("none"))}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsTraffic")}
                </p>
                <DetailRow label={tl("used")}>{formatBytes(num(infoRow.used_bytes), isFa)}</DetailRow>
                <DetailRow label={tl("cap")}>
                  {num(infoRow.total_gb) < 1 && num(infoRow.limit_bytes) < 1
                    ? tl("unlimited")
                    : `${formatNumber(num(infoRow.total_gb), isFa)} ${tl("gbUnit")} · ${formatBytes(num(infoRow.limit_bytes), isFa)}`}
                </DetailRow>
                <DetailRow label={tl("fieldLimitIp")}>{formatNumber(num(infoRow.limit_ip), isFa)}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsExpiry")}
                </p>
                <DetailRow label={tl("expiryUnified")}>{unifiedExpirySummary(infoRow)}</DetailRow>
                {expirySourcesDiffer(infoRow) ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">{tl("expiryMismatchHint")}</p>
                ) : null}
                <DetailRow label={tl("fieldStartAfterFirstUse")}>
                  {num(infoRow.first_usage) !== 0 ? tl("yes") : tl("no")}
                </DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsLink")}
                </p>
                <DetailRow label={tl("linkStatus")}>
                  {num(infoRow.is_linked) !== 0 ? tl("linkStatusLinked") : tl("linkStatusUnlinked")}
                </DetailRow>
                <DetailRow label="linked_service_id">{String(num(infoRow.linked_service_id) || tl("none"))}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsEndpoints")}
                </p>
                <DetailRow label={tl("qrSub")}>
                  {String(infoRow.subscription_url ?? "").trim() ? (
                    <span className="font-mono text-xs">{String(infoRow.subscription_url)}</span>
                  ) : (
                    tl("noSubUrl")
                  )}
                </DetailRow>
                <DetailRow label={tl("qrCfg")}>
                  {String(infoRow.primary_config_uri ?? "").trim() ? (
                    <span className="font-mono text-xs">{String(infoRow.primary_config_uri)}</span>
                  ) : (
                    tl("noCfgUri")
                  )}
                </DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tl("detailsIps")}
                </p>
                <DetailRow label={tl("ipsTitle")}>
                  {parseClientIps(infoRow).length ? parseClientIps(infoRow).join(", ") : tl("ipsEmpty")}
                </DetailRow>
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog
        open={Boolean(ipsTarget)}
        onOpenChange={(open) => {
          if (!open) setIpsTarget(null)
        }}
      >
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("ipsTitle")}</DialogTitle>
          </DialogHeader>
          {ipsTarget ? (
            <div className="space-y-2">
              <p className="break-all font-mono text-xs text-muted-foreground">{String(ipsTarget.row.email ?? "")}</p>
              {(() => {
                const ips = parseClientIps(ipsTarget.row)
                return ips.length ? (
                  <ul className="max-h-64 list-inside list-disc space-y-1 overflow-y-auto text-sm">
                    {ips.map((ip) => (
                      <li key={ip} className="font-mono">
                        {ip}
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm text-muted-foreground">{tl("ipsEmpty")}</p>
                )
              })()}
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={qrOpen} onOpenChange={setQrOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("qrTitle")}</DialogTitle>
          </DialogHeader>
          <p className="text-xs text-muted-foreground">{tl("qrClickCopyHint")}</p>
          {qrCopyHint ? (
            <p
              className={cn(
                "text-sm",
                qrCopyHint.startsWith("__err__") ? "text-destructive" : "text-green-600 dark:text-green-400"
              )}
            >
              {qrCopyHint.startsWith("__err__") ? qrCopyHint.slice(7) : qrCopyHint}
            </p>
          ) : null}
          {qrRow ? (
            <div className="grid gap-8 sm:grid-cols-2">
              <div className="grid justify-items-center gap-3">
                <div className="text-sm font-medium">{tl("qrSub")}</div>
                {String(qrRow.subscription_url ?? "").trim() ? (
                  <button
                    type="button"
                    className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                    onClick={() => void copyToClipboard(String(qrRow.subscription_url)).then((ok) => showQrCopy(ok ? "ok" : "fail"))}
                  >
                    <QRCodeSVG value={String(qrRow.subscription_url)} size={168} level="M" />
                    <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <Copy className="size-3" /> {tl("copyAction")}
                    </span>
                  </button>
                ) : (
                  <p className="text-xs text-muted-foreground">{tl("noSubUrl")}</p>
                )}
              </div>
              <div className="grid justify-items-center gap-3">
                <div className="text-sm font-medium">{tl("qrCfg")}</div>
                {String(qrRow.primary_config_uri ?? "").trim() ? (
                  <button
                    type="button"
                    className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                    onClick={() => void copyToClipboard(String(qrRow.primary_config_uri)).then((ok) => showQrCopy(ok ? "ok" : "fail"))}
                  >
                    <QRCodeSVG value={String(qrRow.primary_config_uri)} size={168} level="M" />
                    <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <Copy className="size-3" /> {tl("copyAction")}
                    </span>
                  </button>
                ) : (
                  <p className="text-xs text-muted-foreground">{tl("noCfgUri")}</p>
                )}
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("editTitle")}</DialogTitle>
          </DialogHeader>
          <div className="grid max-h-[70vh] gap-3 overflow-y-auto py-2 pe-1">
            <div className="grid gap-1">
              <Label>{tl("fieldRemark")}</Label>
              <Input value={editRemark} onChange={(e) => setEditRemark(e.target.value)} />
            </div>
            <div className="grid gap-1">
              <Label>{tl("fieldAdminComment")}</Label>
              <Textarea value={editClientComment} onChange={(e) => setEditClientComment(e.target.value)} rows={3} />
            </div>
            <div className="grid gap-1">
              <Label>{tl("fieldLimitIp")}</Label>
              <Input inputMode="numeric" value={editLimitIp} onChange={(e) => setEditLimitIp(e.target.value)} />
            </div>
            <div className={cn("flex items-center justify-between gap-3 rounded-md border border-border/50 px-3 py-2", flexRow)}>
              <Label htmlFor="cfg-safu" className="cursor-pointer text-sm">
                {tl("fieldStartAfterFirstUse")}
              </Label>
              <Switch
                id="cfg-safu"
                checked={editStartAfterFirstUse}
                onCheckedChange={setEditStartAfterFirstUse}
                aria-label={tl("fieldStartAfterFirstUse")}
              />
            </div>
            <div className="grid gap-1">
              <Label>{tl("fieldTotalGb")}</Label>
              <Input inputMode="numeric" value={editTotalGb} onChange={(e) => setEditTotalGb(e.target.value)} />
            </div>
            <div className="grid gap-1">
              <Label>{isFa ? tl("fieldExpiryShamsi") : tl("fieldExpiry")}</Label>
              {isFa ? (
                <ConfigJalaliExpiryFields
                  valueMs={editExpiryMs}
                  onChangeMs={setEditExpiryMs}
                  tl={(k) => tl(k)}
                  selectClass={selectClass}
                />
              ) : (
                <Input
                  type="datetime-local"
                  value={editExpiryMs > 0 ? msToDatetimeLocalValue(editExpiryMs) : ""}
                  onChange={(e) => {
                    const ms = datetimeLocalToMs(e.target.value)
                    setEditExpiryMs(ms > 0 ? ms : 0)
                  }}
                />
              )}
            </div>
          </div>
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button
              type="button"
              disabled={busyRow === rowKey(editPanelId, editInboundId, editEmail)}
              onClick={() => void onSaveEdit()}
            >
              {tl("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={delOpen} onOpenChange={setDelOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("deleteOneTitle")}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {delRow && num(delRow.linked_service_id) > 0 ? tl("deleteOneLinked") : tl("deleteOneOrphan")}
          </p>
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setDelOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button type="button" variant="destructive" onClick={() => void onDelete()}>
              {tl("delete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={resetOpen} onOpenChange={setResetOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("resetTrafficTitle")}</DialogTitle>
          </DialogHeader>
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setResetOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button type="button" onClick={() => void onResetTraffic()}>
              {tl("resetTraffic")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={quickOpen} onOpenChange={setQuickOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>{tl("quickAdd")}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">{tl("quickAddHint")}</p>
          {merged && merged.default_svp_user_id < 1 ? (
            <p className="text-xs text-amber-600 dark:text-amber-400">{tl("defaultUserHint")}</p>
          ) : null}
          <div className="grid gap-1">
            <Label>{tl("targetUser")} (svp_users.id)</Label>
            <Input
              placeholder={merged && merged.default_svp_user_id > 0 ? String(merged.default_svp_user_id) : ""}
              value={quickTarget}
              onChange={(e) => setQuickTarget(e.target.value)}
            />
          </div>
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setQuickOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button type="button" disabled={busyRow === `quick:${quickPlanId}`} onClick={() => void runQuickAdd()}>
              {tl("createService")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={linkOpen} onOpenChange={setLinkOpen}>
        <DialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DialogHeader className={dialogHeaderClass}>
            <DialogTitle>
              {linkCtx && num(linkCtx.row.is_linked) !== 0 ? tl("linkUserEdit") : tl("linkUserAdd")}
            </DialogTitle>
          </DialogHeader>
          {linkCtx ? (
            <div className="space-y-3">
              <p className="break-all font-mono text-xs text-muted-foreground">{String(linkCtx.row.email ?? "")}</p>
              <div className="grid gap-1">
                <Label>{tl("userSearchPlaceholder")}</Label>
                <Input
                  placeholder={tl("userSearchPlaceholder")}
                  value={linkQuery}
                  onChange={(e) => {
                    const v = e.target.value
                    setLinkQuery(v)
                    scheduleLinkSearch(v)
                  }}
                />
              </div>
              {linkHits.length > 0 ? (
                <div className="max-h-32 overflow-y-auto rounded border border-border/60 bg-muted/20 p-1 text-xs">
                  {linkHits.map((u) => (
                    <button
                      key={num(u.id)}
                      type="button"
                      className={cn(
                        "block w-full truncate rounded px-2 py-1 hover:bg-muted",
                        isFa ? "text-end" : "text-start"
                      )}
                      onClick={() => setLinkPick({ id: num(u.id), label: userRowLabel(u) })}
                    >
                      {userRowLabel(u)}
                    </button>
                  ))}
                </div>
              ) : null}
              {linkPick ? (
                <p className="text-xs text-muted-foreground">
                  {linkPick.label}
                  <button
                    type="button"
                    className="ms-2 underline"
                    onClick={() => setLinkPick(null)}
                  >
                    {t("inboundLinkAdmin.clearPick")}
                  </button>
                </p>
              ) : null}
            </div>
          ) : null}
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setLinkOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={!linkCtx || busyRow === (linkCtx ? rowKey(linkCtx.panel_id, linkCtx.inbound_id, String(linkCtx.row.email ?? "")) : "")}
              onClick={() => void submitLink()}
            >
              {tl("link")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { QRCodeSVG } from "qrcode.react"
import {
  ChevronDown,
  Info,
  Network,
  Pencil,
  QrCode,
  RotateCcw,
  Trash2,
  UserPlus,
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
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

const CONFIGS_BATCH_MAX = 40

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function rowKey(inboundId: number, email: string): string {
  return `${inboundId}::${email}`
}

function formatBytesShort(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return "0 B"
  const u = ["B", "KB", "MB", "GB", "TB"]
  let b = bytes
  let i = 0
  while (b >= 1024 && i < u.length - 1) {
    b /= 1024
    i++
  }
  const rounded = b >= 100 || i === 0 ? Math.round(b) : Math.round(b * 10) / 10
  return `${rounded} ${u[i]}`
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
  email?: string
  enable?: number
  is_online?: number
  used_bytes?: number
  limit_bytes?: number
  total_gb?: number
  expiry_ms?: number
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

type PlanGroup = {
  plan: DashRecord
  inbound_id: number
  inbound_remark?: string
  protocol?: string
  port?: number
  clients: ClientRow[]
}

type UserPick = { id: number; label: string }

export function DashboardConfigsAdmin({
  panels,
  isFa,
  onMutateSuccess,
}: {
  panels: DashRecord[]
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tl = (k: string, opts?: Record<string, string | number>) => t(`configsAdmin.${k}`, opts)

  const [panelId, setPanelId] = useState<number>(() => (panels.length ? num(panels[0].id) : 0))
  const [loadBusy, setLoadBusy] = useState(false)
  const [snapshot, setSnapshot] = useState<{
    plans: PlanGroup[]
    default_svp_user_id: number
    truncated: number
    expired_linked_batch_count: number
  } | null>(null)
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const [infoOpen, setInfoOpen] = useState(false)
  const [infoRow, setInfoRow] = useState<ClientRow | null>(null)

  const [qrOpen, setQrOpen] = useState(false)
  const [qrRow, setQrRow] = useState<ClientRow | null>(null)

  const [editOpen, setEditOpen] = useState(false)
  const [editInboundId, setEditInboundId] = useState(0)
  const [editEmail, setEditEmail] = useState("")
  const [editRemark, setEditRemark] = useState("")
  const [editTotalGb, setEditTotalGb] = useState("0")
  const [editExpiryLocal, setEditExpiryLocal] = useState("")

  const [delOpen, setDelOpen] = useState(false)
  const [delRow, setDelRow] = useState<ClientRow | null>(null)
  const [delInboundId, setDelInboundId] = useState(0)

  const [resetOpen, setResetOpen] = useState(false)
  const [resetRow, setResetRow] = useState<ClientRow | null>(null)
  const [resetInboundId, setResetInboundId] = useState(0)

  const [quickOpen, setQuickOpen] = useState(false)
  const [quickPlanId, setQuickPlanId] = useState(0)
  const [quickTarget, setQuickTarget] = useState("")

  const [expConfirm, setExpConfirm] = useState("")
  const [expDeleteAck, setExpDeleteAck] = useState(false)
  const [expBusy, setExpBusy] = useState(false)

  const [bulkSel, setBulkSel] = useState<Record<string, { inbound_id: number; email: string }>>({})
  const [ipsTarget, setIpsTarget] = useState<{ inbound_id: number; row: ClientRow } | null>(null)
  const [batchBusy, setBatchBusy] = useState(false)

  const [uidInputs, setUidInputs] = useState<Record<string, string>>({})
  const [userHits, setUserHits] = useState<Record<string, DashRecord[]>>({})
  const [userPick, setUserPick] = useState<Record<string, UserPick | null>>({})
  const searchTimers = useRef<Record<string, ReturnType<typeof setTimeout> | undefined>>({})

  const [busyRow, setBusyRow] = useState<string | null>(null)

  const bulkCount = useMemo(() => Object.keys(bulkSel).length, [bulkSel])

  const panelOptions = useMemo(() => {
    return panels.map((p) => ({
      id: num(p.id),
      label: String(p.label ?? "").trim() || `#${num(p.id)}`,
    }))
  }, [panels])

  const loadSnapshot = useCallback(async () => {
    if (panelId < 1) {
      setErr(tl("pickPanel"))
      return
    }
    setErr(null)
    setMsg(null)
    setLoadBusy(true)
    try {
      const json = await getAdminJson("/dashboard/admin/configs-snapshot", { panel_id: panelId })
      if (!json.ok) {
        setErr(String(json.message ?? tl("loadFailed")))
        setSnapshot(null)
        return
      }
      const data = json.data as Record<string, unknown> | undefined
      const rawPlans = data && Array.isArray(data.plans) ? (data.plans as PlanGroup[]) : []
      setSnapshot({
        plans: rawPlans,
        default_svp_user_id: num(data?.default_svp_user_id),
        truncated: num(data?.truncated),
        expired_linked_batch_count: num(data?.expired_linked_batch_count),
      })
      setExpConfirm("")
      setExpDeleteAck(false)
      setBulkSel({})
      setMsg(tl("refresh"))
    } finally {
      setLoadBusy(false)
    }
  }, [panelId, tl])

  useEffect(() => {
    return () => {
      for (const k of Object.keys(searchTimers.current)) {
        const x = searchTimers.current[k]
        if (x) clearTimeout(x)
      }
    }
  }, [])

  const scheduleUserSearch = useCallback((key: string, q: string) => {
    const prev = searchTimers.current[key]
    if (prev) clearTimeout(prev)
    const trimmed = q.trim()
    if (trimmed.length < 2) {
      setUserHits((h) => ({ ...h, [key]: [] }))
      return
    }
    searchTimers.current[key] = setTimeout(() => {
      void (async () => {
        try {
          const json = await getAdminJson("/dashboard/admin/user-search", { q: trimmed })
          if (!json.ok) return
          const users = Array.isArray(json.users) ? (json.users as DashRecord[]) : []
          setUserHits((h) => ({ ...h, [key]: users }))
        } catch {
          /* ignore */
        }
      })()
    }, 320)
  }, [])

  const afterMutate = useCallback(async () => {
    onMutateSuccess?.()
    await loadSnapshot()
  }, [loadSnapshot, onMutateSuccess])

  const setToggleBusy = (rk: string | null) => setBusyRow(rk)

  const onToggleEnable = useCallback(
    async (inboundId: number, row: ClientRow, enabled: boolean) => {
      const email = String(row.email ?? "")
      const rk = rowKey(inboundId, email)
      setErr(null)
      setToggleBusy(rk)
      try {
        const res = await postAdminMutate("configs_client_toggle_enable", {
          panel_id: panelId,
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
        setToggleBusy(null)
      }
    },
    [afterMutate, panelId, tl]
  )

  const onResetTraffic = useCallback(async () => {
    if (!resetRow || resetInboundId < 1) return
    const email = String(resetRow.email ?? "")
    const rk = rowKey(resetInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_reset_traffic", {
        panel_id: panelId,
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
  }, [afterMutate, panelId, resetInboundId, resetRow, tl])

  const onDelete = useCallback(async () => {
    if (!delRow || delInboundId < 1) return
    const email = String(delRow.email ?? "")
    const rk = rowKey(delInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_delete", {
        panel_id: panelId,
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
  }, [afterMutate, delInboundId, delRow, panelId, tl])

  const onSaveEdit = useCallback(async () => {
    if (editInboundId < 1 || !editEmail) return
    setErr(null)
    setBusyRow(rowKey(editInboundId, editEmail))
    try {
      const payload: Record<string, unknown> = {
        panel_id: panelId,
        inbound_id: editInboundId,
        email: editEmail,
        client_remark: editRemark,
        total_gb: parseInt(editTotalGb, 10) || 0,
      }
      if (editExpiryLocal.trim()) {
        const ms = datetimeLocalToMs(editExpiryLocal)
        if (ms > 0) payload.expiry_ms = ms
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
  }, [afterMutate, editEmail, editExpiryLocal, editInboundId, editRemark, editTotalGb, panelId, tl])

  const linkOne = useCallback(
    async (inboundId: number, email: string) => {
      const lk = rowKey(inboundId, email)
      const pick = userPick[lk]
      const q = (uidInputs[lk] ?? "").trim()
      const body: Record<string, unknown> = {
        inbound_id: inboundId,
        panel_id: panelId,
        email,
      }
      if (pick && pick.id > 0) {
        body.user_id = pick.id
      } else if (q.length >= 2) {
        body.user_query = q
      } else {
        const uid = parseInt(q, 10)
        if (Number.isFinite(uid) && uid >= 1) {
          body.user_id = uid
        } else {
          setErr(t("inboundLinkAdmin.badLinkParams"))
          return
        }
      }
      setErr(null)
      setBusyRow(lk)
      try {
        const res = await postAdminMutate("inbound_link", body)
        if (!res.ok) {
          const rsn = res.reason
          if (rsn === "ambiguous") setErr(t("inboundLinkAdmin.resolveAmbiguous"))
          else if (rsn === "not_found" || rsn === "empty") setErr(t("inboundLinkAdmin.resolveNotFound"))
          else setErr(res.message ?? "error")
          return
        }
        setUidInputs((m) => ({ ...m, [lk]: "" }))
        setUserPick((m) => ({ ...m, [lk]: null }))
        setUserHits((m) => ({ ...m, [lk]: [] }))
        await afterMutate()
      } finally {
        setBusyRow(null)
      }
    },
    [afterMutate, panelId, uidInputs, userPick, t]
  )

  const runQuickAdd = useCallback(async () => {
    const def = snapshot?.default_svp_user_id ?? 0
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
  }, [afterMutate, quickPlanId, quickTarget, snapshot?.default_svp_user_id, t, tl])

  const runDeleteExpired = useCallback(async () => {
    const n = snapshot?.expired_linked_batch_count ?? 0
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
        panel_id: panelId,
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
  }, [afterMutate, expConfirm, expDeleteAck, panelId, snapshot?.expired_linked_batch_count, tl])

  const toggleBulkRow = useCallback((rk: string, inboundId: number, email: string, checked: boolean) => {
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
      return { ...prev, [rk]: { inbound_id: inboundId, email } }
    })
  }, [tl])

  const runClientsBatch = useCallback(
    async (batch_op: "reset_traffic" | "set_enable", enable?: boolean) => {
      const items = Object.values(bulkSel)
      if (items.length < 1) return
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
          panel_id: panelId,
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
          setMsg(tl("refresh"))
        }
        setBulkSel({})
        await afterMutate()
      } finally {
        setBatchBusy(false)
      }
    },
    [afterMutate, bulkSel, panelId, tl]
  )

  const openEdit = (inboundId: number, row: ClientRow) => {
    const email = String(row.email ?? "")
    setEditInboundId(inboundId)
    setEditEmail(email)
    setEditRemark(String(row.remark ?? row.comment ?? ""))
    setEditTotalGb(String(num(row.total_gb)))
    const ms = num(row.expiry_ms)
    setEditExpiryLocal(ms > 0 ? msToDatetimeLocalValue(ms) : "")
    setEditOpen(true)
  }

  const progressVal = (row: ClientRow) => {
    const lim = num(row.limit_bytes)
    const used = num(row.used_bytes)
    if (lim <= 0) return 0
    return Math.min(100, Math.round((100 * used) / lim))
  }

  const expiryLabel = (row: ClientRow) => {
    const ms = num(row.expiry_ms)
    if (ms < 1) return tl("noPanelExpiry")
    const left = ms - Date.now()
    if (left <= 0) return isFa ? "منقضی" : "Expired"
    const d = Math.ceil(left / 86400000)
    return isFa ? `${d} روز` : `${d}d left`
  }

  return (
    <div className={cn("space-y-6", isFa && "text-right")}>
      <div>
        <h2 className="text-lg font-medium">{tl("title")}</h2>
        <p className="text-sm text-muted-foreground">{tl("subtitle")}</p>
      </div>

      <div className="flex flex-col gap-4 rounded-lg border border-border/60 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <div className="grid gap-2">
          <Label>{tl("fieldPanel")}</Label>
          <select
            className={cn(
              "flex h-9 w-full max-w-xs rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm",
              isFa && "text-right"
            )}
            value={panelId || ""}
            onChange={(e) => {
              const v = parseInt(e.target.value, 10)
              setPanelId(Number.isFinite(v) ? v : 0)
              setSnapshot(null)
              setBulkSel({})
              setErr(null)
              setMsg(null)
            }}
          >
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
        <Button type="button" variant="secondary" disabled={loadBusy || panelId < 1} onClick={() => void loadSnapshot()}>
          {loadBusy ? tl("loading") : tl("loadSnapshot")}
        </Button>
      </div>

      {snapshot && snapshot.truncated ? (
        <p className="text-xs text-amber-600 dark:text-amber-400">{tl("truncated")}</p>
      ) : null}

      {snapshot && snapshot.expired_linked_batch_count > 0 ? (
        <div className="space-y-2 rounded-lg border border-destructive/40 bg-destructive/5 p-4">
          <p className="text-sm font-medium">{tl("deleteExpired")}</p>
          <p className="text-xs text-muted-foreground">{tl("deleteExpiredHint")}</p>
          <label className="flex items-start gap-2 text-xs text-muted-foreground">
            <input
              type="checkbox"
              className="mt-0.5"
              checked={expDeleteAck}
              onChange={(e) => setExpDeleteAck(e.target.checked)}
            />
            <span>{tl("deleteExpiredAck")}</span>
          </label>
          <div className="flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
              <Label className="text-xs">{tl("confirmCount")}</Label>
              <Input
                className="w-40"
                inputMode="numeric"
                value={expConfirm}
                onChange={(e) => setExpConfirm(e.target.value)}
                placeholder={String(snapshot.expired_linked_batch_count)}
              />
            </div>
            <Button
              type="button"
              variant="destructive"
              disabled={expBusy}
              onClick={() => void runDeleteExpired()}
            >
              {expBusy ? tl("loading") : tl("runDeleteExpired")}
            </Button>
          </div>
        </div>
      ) : null}

      {msg ? <p className="text-sm text-green-600 dark:text-green-400">{msg}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}

      {bulkCount > 0 ? (
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
            <Button
              type="button"
              size="sm"
              disabled={batchBusy}
              onClick={() => void runClientsBatch("set_enable", true)}
            >
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

      {snapshot && snapshot.plans.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tl("noPlans")}</p>
      ) : null}

      {snapshot?.plans.map((pg) => {
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
          <Collapsible key={`${planId}-${iid}`} defaultOpen className="group/collapsible rounded-lg border border-border/60">
            <div className="flex items-stretch gap-0 border-b border-border/60">
              <CollapsibleTrigger asChild>
                <button
                  type="button"
                  className="flex min-w-0 flex-1 items-center gap-2 p-3 text-start hover:bg-muted/40"
                >
                  <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                  <div className="min-w-0">
                    <div className="font-medium">{planName}</div>
                    <div className="text-xs text-muted-foreground">{sub}</div>
                  </div>
                </button>
              </CollapsibleTrigger>
              <div className="flex shrink-0 items-center border-s border-border/60 px-2">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busyRow === `quick:${planId}` || planId < 1}
                  onClick={(e) => {
                    e.preventDefault()
                    e.stopPropagation()
                    setQuickPlanId(planId)
                    setQuickTarget(snapshot.default_svp_user_id > 0 ? String(snapshot.default_svp_user_id) : "")
                    setQuickOpen(true)
                  }}
                >
                  <UserPlus className="size-4" />
                  <span className="sr-only md:not-sr-only md:inline">{tl("quickAdd")}</span>
                </Button>
              </div>
            </div>
            <CollapsibleContent>
              <div className="border-t border-border/60">
                {pg.clients.length === 0 ? (
                  <p className="p-3 text-sm text-muted-foreground">{tl("noPlans")}</p>
                ) : (
                  pg.clients.map((row) => {
                    const email = String(row.email ?? "")
                    const rk = rowKey(iid, email)
                    const enabled = num(row.enable) !== 0
                    const online = num(row.is_online) === 1
                    const lk = rk
                    return (
                      <div
                        key={rk}
                        className={cn(
                          "flex min-h-14 flex-col gap-2 border-b border-border/50 px-3 py-2 last:border-b-0 sm:flex-row sm:items-center",
                          isFa && "sm:flex-row-reverse"
                        )}
                      >
                        <div className="flex flex-wrap items-center gap-2 sm:min-w-[220px] sm:max-w-[min(100%,280px)]">
                          <input
                            type="checkbox"
                            checked={Boolean(bulkSel[rk])}
                            disabled={batchBusy || busyRow === rk}
                            onChange={(e) => toggleBulkRow(rk, iid, email, e.target.checked)}
                            aria-label={tl("batchSelectRow")}
                          />
                          <input
                            type="checkbox"
                            checked={enabled}
                            disabled={batchBusy || busyRow === rk}
                            onChange={(e) => void onToggleEnable(iid, row, e.target.checked)}
                            aria-label={tl("enable")}
                          />
                          <Badge variant={online ? "default" : "secondary"}>{online ? tl("online") : tl("offline")}</Badge>
                          <span className="truncate font-mono text-xs" title={email}>
                            {email}
                          </span>
                        </div>
                        <div className="min-w-0 flex-1 space-y-1">
                          <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span>
                              {tl("used")}: {formatBytesShort(num(row.used_bytes))}
                            </span>
                            <span>
                              {tl("cap")}: {num(row.total_gb) < 1 && num(row.limit_bytes) < 1 ? tl("unlimited") : `${num(row.total_gb)} GB`}
                            </span>
                            <span>
                              {tl("expiryPanel")}: {expiryLabel(row)}
                            </span>
                            {row.service_expires_at ? (
                              <span>
                                {tl("serviceDbExpiry")}: {String(row.service_expires_at)}
                              </span>
                            ) : null}
                          </div>
                          {num(row.limit_bytes) > 0 ? (
                            <Progress value={progressVal(row)} className="h-1.5 max-w-md" />
                          ) : null}
                        </div>
                        <div className={cn("flex flex-wrap items-center gap-1", isFa && "sm:flex-row-reverse")}>
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-8"
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
                                className="size-8"
                                onClick={() => {
                                  setQrRow(row)
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
                                className="size-8"
                                onClick={() => openEdit(iid, row)}
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
                                className="size-8"
                                onClick={() => setIpsTarget({ inbound_id: iid, row })}
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
                            className="size-8"
                            disabled={batchBusy || busyRow === rk}
                            onClick={() => {
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
                            className="size-8 text-destructive"
                            disabled={batchBusy || busyRow === rk}
                            onClick={() => {
                              setDelInboundId(iid)
                              setDelRow(row)
                              setDelOpen(true)
                            }}
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                        {num(row.is_linked) === 0 ? (
                          <div className="flex w-full flex-col gap-2 border-t border-dashed border-border/60 pt-2 sm:w-auto sm:border-t-0 sm:pt-0">
                            <div className="grid gap-1">
                              <Label className="text-xs">{tl("linkUser")}</Label>
                              <Input
                                className="max-w-xs"
                                placeholder={tl("userSearchPlaceholder")}
                                value={uidInputs[lk] ?? ""}
                                onChange={(e) => {
                                  const v = e.target.value
                                  setUidInputs((m) => ({ ...m, [lk]: v }))
                                  scheduleUserSearch(lk, v)
                                }}
                              />
                            </div>
                            {userHits[lk] && userHits[lk].length > 0 ? (
                              <div className="max-h-28 max-w-md overflow-y-auto rounded border border-border/60 bg-muted/20 p-1 text-xs">
                                {userHits[lk].map((u) => (
                                  <button
                                    key={num(u.id)}
                                    type="button"
                                    className="block w-full truncate rounded px-2 py-1 text-start hover:bg-muted"
                                    onClick={() =>
                                      setUserPick((m) => ({
                                        ...m,
                                        [lk]: { id: num(u.id), label: userRowLabel(u) },
                                      }))
                                    }
                                  >
                                    {userRowLabel(u)}
                                  </button>
                                ))}
                              </div>
                            ) : null}
                            {userPick[lk] ? (
                              <p className="text-xs text-muted-foreground">
                                {userPick[lk]?.label}
                                <button
                                  type="button"
                                  className="ms-2 underline"
                                  onClick={() => setUserPick((m) => ({ ...m, [lk]: null }))}
                                >
                                  {t("inboundLinkAdmin.clearPick")}
                                </button>
                              </p>
                            ) : null}
                            <Button
                              type="button"
                              size="sm"
                              variant="secondary"
                              disabled={batchBusy || busyRow === rk}
                              onClick={() => void linkOne(iid, email)}
                            >
                              {tl("link")}
                            </Button>
                          </div>
                        ) : null}
                      </div>
                    )
                  })
                )}
              </div>
            </CollapsibleContent>
          </Collapsible>
        )
      })}

      <Dialog open={infoOpen} onOpenChange={setInfoOpen}>
        <DialogContent className={cn("max-w-lg", isFa && "text-right")}>
          <DialogHeader>
            <DialogTitle>{tl("infoTitle")}</DialogTitle>
          </DialogHeader>
          {infoRow ? (
            <pre className="max-h-80 overflow-auto rounded-md bg-muted/50 p-3 text-xs">{JSON.stringify(infoRow, null, 2)}</pre>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog
        open={Boolean(ipsTarget)}
        onOpenChange={(open) => {
          if (!open) setIpsTarget(null)
        }}
      >
        <DialogContent className={cn("max-w-md", isFa && "text-right")}>
          <DialogHeader>
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
        <DialogContent className={cn("max-w-lg", isFa && "text-right")}>
          <DialogHeader>
            <DialogTitle>{tl("qrTitle")}</DialogTitle>
          </DialogHeader>
          {qrRow ? (
            <div className="grid gap-6 sm:grid-cols-2">
              <div className="grid justify-items-center gap-2">
                <div className="text-sm font-medium">{tl("qrSub")}</div>
                {String(qrRow.subscription_url ?? "").trim() ? (
                  <QRCodeSVG value={String(qrRow.subscription_url)} size={160} level="M" />
                ) : (
                  <p className="text-xs text-muted-foreground">{tl("noSubUrl")}</p>
                )}
              </div>
              <div className="grid justify-items-center gap-2">
                <div className="text-sm font-medium">{tl("qrCfg")}</div>
                {String(qrRow.primary_config_uri ?? "").trim() ? (
                  <QRCodeSVG value={String(qrRow.primary_config_uri)} size={160} level="M" />
                ) : (
                  <p className="text-xs text-muted-foreground">{tl("noCfgUri")}</p>
                )}
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className={cn("max-w-md", isFa && "text-right")}>
          <DialogHeader>
            <DialogTitle>{tl("editTitle")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            <div className="grid gap-1">
              <Label>{tl("fieldRemark")}</Label>
              <Input value={editRemark} onChange={(e) => setEditRemark(e.target.value)} />
            </div>
            <div className="grid gap-1">
              <Label>{tl("fieldTotalGb")}</Label>
              <Input inputMode="numeric" value={editTotalGb} onChange={(e) => setEditTotalGb(e.target.value)} />
            </div>
            <div className="grid gap-1">
              <Label>{tl("fieldExpiry")}</Label>
              <Input type="datetime-local" value={editExpiryLocal} onChange={(e) => setEditExpiryLocal(e.target.value)} />
            </div>
          </div>
          <DialogFooter className={cn(isFa && "flex-row-reverse")}>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              {tl("cancel")}
            </Button>
            <Button type="button" disabled={busyRow === rowKey(editInboundId, editEmail)} onClick={() => void onSaveEdit()}>
              {tl("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={delOpen} onOpenChange={setDelOpen}>
        <DialogContent className={cn(isFa && "text-right")}>
          <DialogHeader>
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
        <DialogContent className={cn(isFa && "text-right")}>
          <DialogHeader>
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
        <DialogContent className={cn("max-w-md", isFa && "text-right")}>
          <DialogHeader>
            <DialogTitle>{tl("quickAdd")}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">{tl("quickAddHint")}</p>
          {snapshot && snapshot.default_svp_user_id < 1 ? (
            <p className="text-xs text-amber-600 dark:text-amber-400">{tl("defaultUserHint")}</p>
          ) : null}
          <div className="grid gap-1">
            <Label>{tl("targetUser")} (svp_users.id)</Label>
            <Input
              placeholder={snapshot && snapshot.default_svp_user_id > 0 ? String(snapshot.default_svp_user_id) : ""}
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
    </div>
  )
}

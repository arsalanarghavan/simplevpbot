"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { dashDir, dashPageRootClass } from "@/lib/dash-locale"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
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

type InboundRow = {
  id: number
  remark: string
  port: number
  protocol: string
}

type ClientRow = {
  email: string
  remark: string
  linked_user_id: number
  linked_user_label: string
  linked_service_id: number
  is_linked: number
  total_gb: number
}

type UserPick = { id: number; label: string }

export function DashboardInboundLinkAdmin({
  panels,
  isFa,
  onMutateSuccess,
}: {
  panels: DashRecord[]
  isFa: boolean
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const tl = (k: string, opts?: Record<string, string | number>) => t(`inboundLinkAdmin.${k}`, opts)

  const [panelId, setPanelId] = useState<number>(() => (panels.length ? num(panels[0].id) : 0))
  const [loadInboundsBusy, setLoadInboundsBusy] = useState(false)
  const [loadClientsBusy, setLoadClientsBusy] = useState(false)
  const [linkBusyEmail, setLinkBusyEmail] = useState<string | null>(null)
  const [autoBusy, setAutoBusy] = useState(false)

  const [inbounds, setInbounds] = useState<InboundRow[]>([])
  const [inboundId, setInboundId] = useState<number>(0)
  const [inboundRemark, setInboundRemark] = useState("")
  const [clients, setClients] = useState<ClientRow[]>([])
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const [onlyUnlinked, setOnlyUnlinked] = useState(false)
  const [uidInputs, setUidInputs] = useState<Record<string, string>>({})
  const [userHits, setUserHits] = useState<Record<string, DashRecord[]>>({})
  const [userPick, setUserPick] = useState<Record<string, UserPick | null>>({})
  const searchTimers = useRef<Record<string, ReturnType<typeof setTimeout> | undefined>>({})

  const panelOptions = useMemo(() => {
    return panels.map((p) => ({
      id: num(p.id),
      label: String(p.label ?? "").trim() || `#${num(p.id)}`,
    }))
  }, [panels])

  const filteredClients = useMemo(() => {
    if (!onlyUnlinked) return clients
    return clients.filter((c) => num(c.is_linked) === 0)
  }, [clients, onlyUnlinked])

  const clientStats = useMemo(() => {
    let linked = 0
    for (const c of clients) {
      if (num(c.is_linked) !== 0 && num(c.linked_user_id) > 0) linked++
    }
    return { total: clients.length, linked, unlinked: clients.length - linked }
  }, [clients])

  const scheduleUserSearch = useCallback((email: string, q: string) => {
    const prev = searchTimers.current[email]
    if (prev) clearTimeout(prev)
    const trimmed = q.trim()
    if (trimmed.length < 2) {
      setUserHits((h) => ({ ...h, [email]: [] }))
      return
    }
    searchTimers.current[email] = setTimeout(() => {
      void (async () => {
        try {
          const json = await getAdminJson("/dashboard/admin/user-search", { q: trimmed })
          if (!json.ok) return
          const users = Array.isArray(json.users) ? (json.users as DashRecord[]) : []
          setUserHits((h) => ({ ...h, [email]: users }))
        } catch {
          /* ignore */
        }
      })()
    }, 320)
  }, [])

  useEffect(() => {
    return () => {
      for (const k of Object.keys(searchTimers.current)) {
        const x = searchTimers.current[k]
        if (x) clearTimeout(x)
      }
    }
  }, [])

  const loadInbounds = useCallback(async () => {
    if (panelId < 1) {
      setErr(tl("pickPanel"))
      return
    }
    setErr(null)
    setMsg(null)
    setLoadInboundsBusy(true)
    try {
      const json = await getAdminJson("/dashboard/admin/panel-inbounds", { panel_id: panelId })
      if (!json.ok) {
        setErr(String(json.message ?? "error"))
        setInbounds([])
        setInboundId(0)
        setClients([])
        return
      }
      const data = json.data as Record<string, unknown> | undefined
      const raw = data && Array.isArray(data.inbounds) ? (data.inbounds as Record<string, unknown>[]) : []
      const list: InboundRow[] = raw.map((r) => ({
        id: num(r.id),
        remark: String(r.remark ?? ""),
        port: num(r.port),
        protocol: String(r.protocol ?? ""),
      }))
      setInbounds(list)
      setInboundId(0)
      setInboundRemark("")
      setClients([])
      setUidInputs({})
      setUserHits({})
      setUserPick({})
      setMsg(tl("inboundsLoaded", { count: list.length }))
    } finally {
      setLoadInboundsBusy(false)
    }
  }, [panelId, tl])

  const loadClients = useCallback(async () => {
    if (panelId < 1 || inboundId < 1) {
      setErr(tl("pickInbound"))
      return
    }
    setErr(null)
    setMsg(null)
    setLoadClientsBusy(true)
    try {
      const json = await getAdminJson("/dashboard/admin/panel-inbound-clients", {
        panel_id: panelId,
        inbound_id: inboundId,
      })
      if (!json.ok) {
        setErr(String(json.message ?? "error"))
        setClients([])
        return
      }
      const data = json.data as Record<string, unknown> | undefined
      const raw = data && Array.isArray(data.clients) ? (data.clients as Record<string, unknown>[]) : []
      const list: ClientRow[] = raw.map((r) => ({
        email: String(r.email ?? ""),
        remark: String(r.remark ?? ""),
        linked_user_id: num(r.linked_user_id),
        linked_user_label: String(r.linked_user_label ?? ""),
        linked_service_id: num(r.linked_service_id),
        is_linked: num(r.is_linked),
        total_gb: num(r.total_gb),
      }))
      setClients(list)
      setUidInputs({})
      setUserHits({})
      setUserPick({})
      const ir = data ? String(data.inb_remark ?? "") : ""
      setInboundRemark(ir)
      setMsg(tl("clientsLoaded", { count: list.length }))
    } finally {
      setLoadClientsBusy(false)
    }
  }, [panelId, inboundId, tl])

  const linkOne = useCallback(
    async (email: string) => {
      if (!email || inboundId < 1 || panelId < 1) {
        setErr(tl("pickInbound"))
        return
      }
      const pick = userPick[email]
      const q = (uidInputs[email] ?? "").trim()
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
          setErr(tl("badLinkParams"))
          return
        }
      }
      setErr(null)
      setMsg(null)
      setLinkBusyEmail(email)
      try {
        const res = await postAdminMutate("inbound_link", body)
        if (!res.ok) {
          const rsn = res.reason
          if (rsn === "ambiguous") setErr(tl("resolveAmbiguous"))
          else if (rsn === "not_found" || rsn === "empty") setErr(tl("resolveNotFound"))
          else setErr(res.message ?? "error")
          return
        }
        setMsg(tl("linkedOk"))
        onMutateSuccess?.()
        await loadClients()
      } finally {
        setLinkBusyEmail(null)
      }
    },
    [uidInputs, userPick, inboundId, panelId, loadClients, onMutateSuccess, tl]
  )

  const runAutolink = useCallback(async () => {
    if (panelId < 1 || inboundId < 1) {
      setErr(tl("pickInbound"))
      return
    }
    if (!window.confirm(tl("autolinkConfirm"))) {
      return
    }
    setErr(null)
    setMsg(null)
    setAutoBusy(true)
    try {
      const res = await postAdminMutate("inbound_autolink", {
        inbound_id: inboundId,
        panel_id: panelId,
      })
      if (!res.ok) {
        setErr(res.message ?? "error")
        return
      }
      setMsg(tl("autolinkOk"))
      onMutateSuccess?.()
      await loadClients()
    } finally {
      setAutoBusy(false)
    }
  }, [panelId, inboundId, loadClients, onMutateSuccess, tl])

  return (
    <div className={dashPageRootClass(isFa)} dir={dashDir(isFa)}>
      <DashboardPageHeader title={tl("title")} description={tl("subtitle")} />

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
              setInbounds([])
              setInboundId(0)
              setClients([])
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
        <Button type="button" variant="secondary" disabled={loadInboundsBusy || panelId < 1} onClick={() => void loadInbounds()}>
          {loadInboundsBusy ? tl("loading") : tl("loadInbounds")}
        </Button>
      </div>

      {inbounds.length > 0 && (
        <div className="flex flex-col gap-4 rounded-lg border border-border/60 p-4 sm:flex-row sm:flex-wrap sm:items-end">
          <div className="grid gap-2">
            <Label>{tl("fieldInbound")}</Label>
            <select
              className={cn(
                "flex h-9 w-full max-w-xl rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm",
                isFa && "text-right"
              )}
              value={inboundId || ""}
              onChange={(e) => {
                const v = parseInt(e.target.value, 10)
                setInboundId(Number.isFinite(v) ? v : 0)
                setClients([])
                setErr(null)
                setMsg(null)
              }}
            >
              <option value="">{tl("selectInbound")}</option>
              {inbounds.map((ib) => (
                <option key={ib.id} value={ib.id}>
                  #{ib.id} — {ib.remark || ib.protocol} ({ib.port})
                </option>
              ))}
            </select>
          </div>
          <Button type="button" variant="secondary" disabled={loadClientsBusy || inboundId < 1} onClick={() => void loadClients()}>
            {loadClientsBusy ? tl("loading") : tl("loadClients")}
          </Button>
          <Button type="button" variant="outline" disabled={autoBusy || inboundId < 1} onClick={() => void runAutolink()}>
            {autoBusy ? tl("loading") : tl("autolink")}
          </Button>
        </div>
      )}

      {inboundRemark ? (
        <p className="text-xs text-muted-foreground">
          {tl("inboundNote")}: {inboundRemark}
        </p>
      ) : null}

      {inbounds.length > 0 || clients.length > 0 ? (
        <div className={cn("flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground")}>
          {inbounds.length > 0 ? <span>{tl("statInbounds", { n: inbounds.length })}</span> : null}
          {clients.length > 0 ? (
            <>
              <span>{tl("statClients", { n: clientStats.total })}</span>
              <span>{tl("statLinked", { n: clientStats.linked })}</span>
              <span>{tl("statUnlinked", { n: clientStats.unlinked })}</span>
            </>
          ) : null}
        </div>
      ) : null}

      {msg ? <p className="text-sm text-green-600 dark:text-green-400">{msg}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}

      {clients.length > 0 && (
        <div className="flex items-center gap-2">
          <input
            id="only-unlinked"
            type="checkbox"
            checked={onlyUnlinked}
            onChange={(e) => setOnlyUnlinked(e.target.checked)}
          />
          <Label htmlFor="only-unlinked" className="cursor-pointer font-normal">
            {tl("onlyUnlinked")}
          </Label>
        </div>
      )}

      {clients.length > 0 && (
        <div className="overflow-x-auto rounded-md border border-border/60">
          <table className="w-full min-w-[720px] text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colEmail")}</th>
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colRemark")}</th>
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colGb")}</th>
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colUser")}</th>
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colService")}</th>
                <th className={cn("p-2 font-medium", "text-start")}>{tl("colLink")}</th>
              </tr>
            </thead>
            <tbody>
              {filteredClients.map((c) => {
                const linked = num(c.is_linked) !== 0 && num(c.linked_user_id) > 0
                const hits = userHits[c.email] ?? []
                const pick = userPick[c.email]
                return (
                  <tr key={c.email} className="border-b border-border/50">
                    <td className="max-w-[200px] break-all p-2 font-mono text-xs">{c.email}</td>
                    <td className="max-w-[160px] break-words p-2">{c.remark || "—"}</td>
                    <td className="p-2">{formatNumber(c.total_gb, isFa)}</td>
                    <td className="p-2">
                      {linked ? (
                        <span>
                          #{formatNumber(c.linked_user_id, isFa)}{" "}
                          {c.linked_user_label ? (
                            <span className="text-muted-foreground">({c.linked_user_label})</span>
                          ) : null}
                        </span>
                      ) : (
                        <Badge variant="secondary">{tl("unlinked")}</Badge>
                      )}
                    </td>
                    <td className="p-2">{c.linked_service_id > 0 ? `#${formatNumber(c.linked_service_id, isFa)}` : "—"}</td>
                    <td className="p-2 align-top">
                      {linked ? (
                        "—"
                      ) : (
                        <div className="flex min-w-[220px] flex-col gap-2">
                          {pick ? (
                            <div className="flex flex-wrap items-center gap-2 rounded border border-border/60 bg-muted/30 px-2 py-1 text-xs">
                              <span className="text-muted-foreground">{pick.label}</span>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 px-2"
                                onClick={() => {
                                  setUserPick((p) => ({ ...p, [c.email]: null }))
                                  setUidInputs((u) => ({ ...u, [c.email]: "" }))
                                  setUserHits((h) => ({ ...h, [c.email]: [] }))
                                }}
                              >
                                {tl("clearPick")}
                              </Button>
                            </div>
                          ) : null}
                          <Input
                            className="h-8 w-full max-w-xs font-mono text-xs"
                            dir="ltr"
                            placeholder={tl("userSearchPlaceholder")}
                            value={uidInputs[c.email] ?? ""}
                            onChange={(e) => {
                              const v = e.target.value
                              setUidInputs((prev) => ({ ...prev, [c.email]: v }))
                              setUserPick((p) => ({ ...p, [c.email]: null }))
                              scheduleUserSearch(c.email, v)
                            }}
                          />
                          <p className="text-[10px] text-muted-foreground">{tl("userSearchHint")}</p>
                          {!pick && hits.length > 0 ? (
                            <div className="max-h-32 overflow-y-auto rounded border border-border/60 bg-background">
                              {hits.map((u) => {
                                const id = num(u.id)
                                if (id < 1) return null
                                const lb = userRowLabel(u)
                                return (
                                  <button
                                    key={id}
                                    type="button"
                                    className={cn(
                                      "block w-full px-2 py-1.5 text-start text-xs hover:bg-muted/60",
                                      isFa && "text-right"
                                    )}
                                    onClick={() => {
                                      setUserPick((p) => ({ ...p, [c.email]: { id, label: lb } }))
                                      setUidInputs((prev) => ({ ...prev, [c.email]: String(id) }))
                                      setUserHits((h) => ({ ...h, [c.email]: [] }))
                                    }}
                                  >
                                    {lb}
                                  </button>
                                )
                              })}
                            </div>
                          ) : null}
                          <Button
                            type="button"
                            size="sm"
                            disabled={linkBusyEmail === c.email}
                            onClick={() => void linkOne(c.email)}
                          >
                            {linkBusyEmail === c.email ? tl("loading") : tl("link")}
                          </Button>
                        </div>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

"use client"

import { useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

const selectClass =
  "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"

type BulkOp = "wallet" | "volume" | "extend" | "alerts"
type JobRow = Record<string, unknown>
type JobItemRow = Record<string, unknown>

export function DashboardUsersBulkAdmin({
  isFa,
  onMutateSuccess,
  canRunBulkWorker = true,
}: {
  isFa: boolean
  onMutateSuccess?: () => void
  /** WP admins may trigger the queue worker; resellers only enqueue jobs. */
  canRunBulkWorker?: boolean
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`usersBulkAdmin.${k}`)
  const isResellerActor = Boolean(window.__SIMPLEVPBOT_DASH__?.isReseller)
  const [scope, setScope] = useState("all_approved")
  const [customIds, setCustomIds] = useState("")
  const [op, setOp] = useState<BulkOp>("wallet")
  const [delta, setDelta] = useState("")
  const [extraGb, setExtraGb] = useState("10")
  const [days, setDays] = useState("3")
  const [reduceMode, setReduceMode] = useState(false)
  const [notify, setNotify] = useState(true)
  const [alertsEnabled, setAlertsEnabled] = useState(true)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [result, setResult] = useState<string>("")
  const [jobs, setJobs] = useState<JobRow[]>([])
  const [selectedJobId, setSelectedJobId] = useState<number | null>(null)
  const [items, setItems] = useState<JobItemRow[]>([])

  async function loadJobs() {
    const r = await getAdminJson("/dashboard/admin/users-bulk-jobs", { page: 1, per_page: 20 })
    const rows = Array.isArray(r.jobs) ? (r.jobs as JobRow[]) : []
    setJobs(rows)
    if (!selectedJobId && rows.length > 0) {
      const id = Number(rows[0]?.id ?? 0)
      if (id > 0) setSelectedJobId(id)
    }
  }

  async function loadItems(jobId: number) {
    const r = await getAdminJson("/dashboard/admin/users-bulk-job-items", {
      job_id: jobId,
      page: 1,
      per_page: 50,
    })
    setItems(Array.isArray(r.rows) ? (r.rows as JobItemRow[]) : [])
  }

  useEffect(() => {
    void loadJobs()
  }, [])

  useEffect(() => {
    if (selectedJobId && selectedJobId > 0) {
      void loadItems(selectedJobId)
    } else {
      setItems([])
    }
  }, [selectedJobId])

  function scopePayload(): Record<string, unknown> {
    if (scope === "custom_ids") {
      const ids = customIds
        .split(/[\s,]+/)
        .map((s) => parseInt(s.trim(), 10))
        .filter((n) => n > 0)
      return { scope: "custom_ids", user_ids: ids }
    }
    return { scope }
  }

  async function dryRun() {
    setBusy(true)
    setErr("")
    setResult("")
    try {
      const base = { ...scopePayload(), dry_run: true, notify }
      let res
      if (op === "wallet") {
        res = await postAdminMutate("users_bulk_wallet", { ...base, delta: Number(delta) })
      } else if (op === "volume") {
        res = await postAdminMutate("users_bulk_volume", {
          ...base,
          extra_gb: Number(extraGb),
          reduce: reduceMode ? 1 : 0,
        })
      } else if (op === "extend") {
        res = await postAdminMutate("users_bulk_extend", {
          ...base,
          days: Number(days),
          reduce: reduceMode ? 1 : 0,
        })
      } else {
        res = await postAdminMutate("users_bulk_alerts", {
          ...base,
          alerts_enabled: alertsEnabled ? 1 : 0,
        })
      }
      if (!res.ok) {
        setErr(res.message || tp("error"))
        return
      }
      setResult(JSON.stringify(res.data, null, 2))
      await loadJobs()
    } finally {
      setBusy(false)
    }
  }

  async function execute() {
    setBusy(true)
    setErr("")
    setResult("")
    try {
      const base = { ...scopePayload(), dry_run: false, notify }
      let res
      if (op === "wallet") {
        res = await postAdminMutate("users_bulk_wallet", { ...base, delta: Number(delta) })
      } else if (op === "volume") {
        res = await postAdminMutate("users_bulk_volume", {
          ...base,
          extra_gb: Number(extraGb),
          reduce: reduceMode ? 1 : 0,
        })
      } else if (op === "extend") {
        res = await postAdminMutate("users_bulk_extend", {
          ...base,
          days: Number(days),
          reduce: reduceMode ? 1 : 0,
        })
      } else {
        res = await postAdminMutate("users_bulk_alerts", {
          ...base,
          alerts_enabled: alertsEnabled ? 1 : 0,
        })
      }
      if (!res.ok) {
        setErr(res.message || tp("error"))
        return
      }
      setResult(JSON.stringify(res.data, null, 2))
      onMutateSuccess?.()
      await loadJobs()
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h2 className="text-lg font-semibold tracking-tight">{tp("title")}</h2>
        <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("scope")}</CardTitle>
          <CardDescription>{tp("scopeHint")}</CardDescription>
          {isResellerActor ? (
            <CardDescription className="text-amber-700 dark:text-amber-400">
              {tp("resellerScopeHint")}
            </CardDescription>
          ) : null}
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>{tp("scopeLabel")}</Label>
            <select className={selectClass} value={scope} onChange={(e) => setScope(e.target.value)}>
              <option value="all_approved">{tp("scopeAllApproved")}</option>
              <option value="approved_with_active_service">{tp("scopeActiveSvc")}</option>
              <option value="custom_ids">{tp("scopeCustom")}</option>
            </select>
          </div>
          {scope === "custom_ids" ? (
            <div className="space-y-2">
              <Label htmlFor="ids">{tp("customIds")}</Label>
              <Input
                id="ids"
                dir="ltr"
                value={customIds}
                onChange={(e) => setCustomIds(e.target.value)}
                placeholder={tp("customIdsPlaceholder")}
              />
            </div>
          ) : null}

          <div className="space-y-2">
            <Label>{tp("operation")}</Label>
            <select className={selectClass} value={op} onChange={(e) => setOp(e.target.value as BulkOp)}>
              <option value="wallet">{tp("opWallet")}</option>
              <option value="volume">{tp("opVolume")}</option>
              <option value="extend">{tp("opExtend")}</option>
              <option value="alerts">{tp("opAlerts")}</option>
            </select>
          </div>

          {op === "wallet" ? (
            <div className="space-y-2">
              <Label htmlFor="delta">{tp("delta")}</Label>
              <Input
                id="delta"
                dir="ltr"
                value={delta}
                onChange={(e) => setDelta(e.target.value)}
                placeholder={tp("deltaPlaceholder")}
              />
            </div>
          ) : null}
          {op === "volume" ? (
            <div className="space-y-2">
              <Label htmlFor="gb">{tp("extraGb")}</Label>
              <Input id="gb" dir="ltr" value={extraGb} onChange={(e) => setExtraGb(e.target.value)} />
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input accent-primary"
                  checked={reduceMode}
                  onChange={(e) => setReduceMode(e.target.checked)}
                />
                {tp("reduceVolume")}
              </label>
            </div>
          ) : null}
          {op === "extend" ? (
            <div className="space-y-2">
              <Label htmlFor="days">{tp("days")}</Label>
              <Input id="days" dir="ltr" value={days} onChange={(e) => setDays(e.target.value)} />
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input accent-primary"
                  checked={reduceMode}
                  onChange={(e) => setReduceMode(e.target.checked)}
                />
                {tp("reduceDays")}
              </label>
            </div>
          ) : null}
          {op === "alerts" ? (
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input accent-primary"
                checked={alertsEnabled}
                onChange={(e) => setAlertsEnabled(e.target.checked)}
              />
              {tp("alertsOn")}
            </label>
          ) : null}

          <label className={cn("flex items-center gap-2 text-sm", isFa && "flex-row-reverse")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input accent-primary"
              checked={notify}
              onChange={(e) => setNotify(e.target.checked)}
            />
            {tp("notifyUsers")}
          </label>

          <div className={cn("flex flex-wrap gap-2", isFa && "flex-row-reverse")}>
            <Button type="button" variant="secondary" disabled={busy} onClick={() => void dryRun()}>
              {tp("dryRun")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void execute()}>
              {tp("execute")}
            </Button>
          </div>
        </CardContent>
      </Card>

      {err ? <p className="text-sm text-destructive">{err}</p> : null}
      {result ? (
        <pre className="overflow-x-auto rounded-lg border bg-muted/30 p-4 text-xs" dir="ltr">
          {result}
        </pre>
      ) : null}

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-2">
          <CardTitle className="text-base">{tp("jobsTitle")}</CardTitle>
          {canRunBulkWorker ? (
            <Button
              type="button"
              size="sm"
              variant="secondary"
              onClick={async () => {
                await postAdminMutate("users_bulk_run_worker", { max_iterations: 20 })
                await loadJobs()
                if (selectedJobId) await loadItems(selectedJobId)
              }}
            >
              {tp("runNow")}
            </Button>
          ) : null}
        </CardHeader>
        <CardContent className="space-y-4">
          {jobs.length < 1 ? (
            <p className="text-sm text-muted-foreground">{tp("jobsEmpty")}</p>
          ) : (
            <div className="grid gap-2">
              {jobs.map((j) => {
                const jid = Number(j.id ?? 0)
                const st = String(j.status ?? "pending")
                const selected = selectedJobId === jid
                return (
                  <button
                    key={jid}
                    type="button"
                    onClick={() => setSelectedJobId(jid)}
                    className={cn(
                      "rounded-md border p-3 text-start text-sm",
                      selected && "border-primary bg-primary/5"
                    )}
                  >
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-mono">#{jid}</span>
                      <span>
                        {t(`usersBulkAdmin.jobStatus_${st}`, { defaultValue: tp("jobStatus_unknown") })}
                      </span>
                    </div>
                    <div className="text-xs text-muted-foreground">
                      {String(j.operation ?? "")} · {String(j.scope ?? "")}
                    </div>
                  </button>
                )
              })}
            </div>
          )}
          {selectedJobId ? (
            <div className="space-y-2">
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={async () => {
                    await postAdminMutate("users_bulk_job_cancel", { job_id: selectedJobId })
                    await loadJobs()
                    await loadItems(selectedJobId)
                  }}
                >
                  {tp("jobStop")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={async () => {
                    await postAdminMutate("users_bulk_job_resume", { job_id: selectedJobId })
                    await loadJobs()
                    await loadItems(selectedJobId)
                  }}
                >
                  {tp("jobResume")}
                </Button>
              </div>
              <div className="overflow-x-auto rounded-md border">
                <table className="w-full min-w-[32rem] text-sm">
                  <thead>
                    <tr className="bg-muted/40">
                      <th className="p-2">{tp("affectedUsers")}</th>
                      <th className="p-2">{tp("colItemStatus")}</th>
                      <th className="p-2">{tp("colTries")}</th>
                      <th className="p-2">{tp("colReason")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((it) => (
                      <tr key={String(it.id ?? "")} className="border-t">
                        <td className="p-2 font-mono">{String(it.user_id ?? "")}</td>
                        <td className="p-2">{String(it.status ?? "")}</td>
                        <td className="p-2">{String(it.tries ?? "0")}</td>
                        <td className="p-2">{String(it.last_error ?? "")}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}

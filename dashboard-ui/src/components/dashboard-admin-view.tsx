import { useCallback, useMemo, useState, type Dispatch, type SetStateAction } from "react"
import { useTranslation } from "react-i18next"
import { DashboardBackupAdmin } from "@/components/dashboard-backup-admin"
import { DashboardBotUiStudio } from "@/components/dashboard-bot-ui-studio"
import { DashboardBotsAdmin } from "@/components/dashboard-bots-admin"
import { DashboardBroadcastAdmin } from "@/components/dashboard-broadcast-admin"
import { DashboardCardsAdmin } from "@/components/dashboard-cards-admin"
import { DashboardDiscountsAdmin } from "@/components/dashboard-discounts-admin"
import { DashboardL2tpAdmin } from "@/components/dashboard-l2tp-admin"
import { DashboardLogsAdmin } from "@/components/dashboard-logs-admin"
import { DashboardNotificationsAdmin } from "@/components/dashboard-notifications-admin"
import { DashboardMonitoring } from "@/components/dashboard-monitoring"
import { DashboardOverview, type OverviewPayload } from "@/components/dashboard-overview"
import { DashboardConfigsAdmin } from "@/components/dashboard-configs-admin"
import { DashboardPanelsAdmin } from "@/components/dashboard-panels-admin"
import { DashboardPlanCatsAdmin } from "@/components/dashboard-plan-cats-admin"
import { DashboardPlansAdmin, type ResellerPanelAccessDiagnostics } from "@/components/dashboard-plans-admin"
import { DashboardReceiptsAdmin } from "@/components/dashboard-receipts-admin"
import { DashboardReferralAdmin } from "@/components/dashboard-referral-admin"
import { DashboardResellerFinance } from "@/components/dashboard-reseller-finance"
import { DashboardResellersAdmin } from "@/components/dashboard-resellers-admin"
import { DashboardTextsAdmin } from "@/components/dashboard-texts-admin"
import { DashboardUsersAdmin } from "@/components/dashboard-users-admin"
import { DashboardUsersBulkAdmin } from "@/components/dashboard-users-bulk-admin"
import { DashboardUserDetailAdmin } from "@/components/dashboard-user-detail-admin"
import { DashboardWholesaleLinesAdmin } from "@/components/dashboard-wholesale-lines-admin"
import { DataPagination } from "@/components/data-pagination"
import {
  listQuerySetPage,
  parsePaginationMeta,
  type ListQueryKey,
  type PaginationMeta,
} from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

type NavTab = { key: string; label: string }

type DashData = {
  settings?: Record<string, unknown> & { enabled?: boolean }
  navTabs?: NavTab[]
  overview?: Record<string, unknown>
} & Record<string, unknown>

function pickPagination(data: DashData, key: string): PaginationMeta | null {
  const raw = data.pagination
  if (!raw || typeof raw !== "object") return null
  return parsePaginationMeta((raw as Record<string, unknown>)[key])
}

const SENSITIVE_RE = /token|secret|password|api_key|_ipn|wallet|crypto_nowpayments|bale_wallet/i

function isSecretKey(k: string): boolean {
  return SENSITIVE_RE.test(k) || k === "panel_password" || k === "panel_login_secret"
}

function redactValue(key: string, val: unknown): string {
  if (val == null) return "—"
  if (isSecretKey(key)) return val === "" || val == null ? "—" : "••••"
  if (key === "card_number" && typeof val === "string" && val.length > 0) {
    const d = val.replace(/[^\d]/g, "")
    return d.length > 4 ? `••••…${d.slice(-4)}` : "••••"
  }
  if (Array.isArray(val)) {
    if (key.toLowerCase().includes("admin_") && key.toLowerCase().endsWith("_ids"))
      return (val as number[]).join(", ")
    return `(${val.length} items)`
  }
  if (typeof val === "object") return "[…]"
  if (String(val).length > 200) return `${String(val).slice(0, 200)}…`
  return String(val)
}

function maskedSettingsObject(obj: Record<string, unknown> | undefined): [string, string][] {
  if (!obj) return []
  return Object.keys(obj)
    .sort()
    .map((k) => [k, redactValue(k, (obj as Record<string, unknown>)[k])])
}

function asRecordArray(x: unknown): Record<string, unknown>[] {
  return Array.isArray(x) ? (x as Record<string, unknown>[]) : []
}

function dashActorSvpUserId(data: DashData): number {
  const u = data.user as Record<string, unknown> | undefined
  const x = Number(u?.svp_user_id)
  return Number.isFinite(x) && x > 0 ? Math.trunc(x) : 0
}

type Props = {
  data: DashData
  activeTab: string
  userDetailId: number | null
  /** Reseller operator (non–WP admin); tighter UI and catalog defaults. */
  isReseller?: boolean
  /** When set, overview/quick links only offer these tabs (already server-filtered). */
  allowedNavTabs?: Set<string> | null
  isFa: boolean
  dashboardBaseUrl: string
  onSelectTab: (tabKey: string) => void
  onOpenUserDetail: (svpUserId: number) => void
  onOpenResellerWorkspace?: (resellerId: number) => void
  onCloseUserDetail: () => void
  setListQuery: Dispatch<SetStateAction<Record<string, string>>>
  usersSearchQuery: string
  onUsersSearchQueryChange: (q: string) => void
  onRefreshPanelHealth?: () => void
  onRefreshLivePanelMetrics?: () => void
  onAdminMutateSuccess?: () => void
  onImpersonateReseller?: (svpUserId: number) => void
}

export function DashboardAdminView({
  data,
  activeTab,
  userDetailId,
  isReseller = false,
  allowedNavTabs = null,
  isFa,
  dashboardBaseUrl,
  onSelectTab,
  onOpenUserDetail,
  onOpenResellerWorkspace,
  onCloseUserDetail,
  setListQuery,
  usersSearchQuery,
  onUsersSearchQueryChange,
  onRefreshPanelHealth,
  onRefreshLivePanelMetrics,
  onAdminMutateSuccess,
  onImpersonateReseller,
}: Props) {
  const { t } = useTranslation()
  const navAllowed = allowedNavTabs
  const setPage = useCallback(
    (key: ListQueryKey, page: number) => {
      setListQuery((q) => listQuerySetPage(q, key, page))
    },
    [setListQuery]
  )
  const setPer = useCallback(
    (key: ListQueryKey, per: number) => {
      setListQuery((q) => listQuerySetPage(q, key, 1, per))
    },
    [setListQuery]
  )

  const settings = data.settings as Record<string, unknown> | undefined
  const rows = useMemo(() => maskedSettingsObject(settings), [settings])
  const [genPage, setGenPage] = useState(1)
  const [genPer, setGenPer] = useState(40)
  const generalMeta: PaginationMeta = useMemo(
    () => ({
      page: genPage,
      perPage: genPer,
      total: rows.length,
    }),
    [genPage, genPer, rows.length]
  )
  const generalSlice = useMemo(() => {
    const start = (genPage - 1) * genPer
    return rows.slice(start, start + genPer)
  }, [rows, genPage, genPer])

  const panels = asRecordArray(data.panels)
  const plans = asRecordArray(data.plans)
  const planCategories = asRecordArray(data.planCategories)
  const cards = asRecordArray(data.cards)
  const l2tp = asRecordArray(data.l2tpServers)
  const texts = asRecordArray(data.texts)
  const users = asRecordArray(data.usersList)
  const pending = asRecordArray(data.pendingUsers)
  const receipts = asRecordArray(data.receipts)
  const disc = asRecordArray(data.discountCodes)
  const broadcasts = asRecordArray(data.broadcasts)
  const resellers = asRecordArray(data.resellers)
  const wholesaleLinesCatalog = asRecordArray((data as Record<string, unknown>).wholesaleLinesCatalog)
  const wholesaleLinesReseller = asRecordArray((data as Record<string, unknown>).wholesaleLines)

  const notFound = (
    <p className="text-sm text-muted-foreground">{t("layout.adminUnknownSection")}</p>
  )

  if (activeTab === "dashboard") {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    return (
      <DashboardOverview
        overview={data.overview as OverviewPayload | undefined}
        panels={panels}
        panelsPagination={pickPagination(data, "panels")}
        isFa={isFa}
        dashboardBaseUrl={dashboardBaseUrl}
        allowedNavTabs={navAllowed}
        onSelectTab={onSelectTab}
        onRefreshPanelHealth={onRefreshPanelHealth}
        onPanelsPageChange={(p) => setPage("panels", p)}
        onPanelsPerPageChange={(n) => setPer("panels", n)}
        compactHealthOnly={isReseller}
        wholesaleLines={wholesaleLinesReseller}
        actorBalance={actorBal}
      />
    )
  }

  if (activeTab === "monitoring") {
    return (
      <DashboardMonitoring
        overview={data.overview as OverviewPayload | undefined}
        panels={panels}
        panelsPagination={pickPagination(data, "panels")}
        monitorHosts={asRecordArray(data.monitorHosts)}
        isFa={isFa}
        onRefreshPanelHealth={onRefreshPanelHealth}
        onRefreshLivePanelMetrics={onRefreshLivePanelMetrics}
        compactHealthOnly={isReseller}
      />
    )
  }

  if (activeTab === "site_settings") {
    return (
      <div className="space-y-4">
        <h2 className="text-lg font-medium">{t("layout.adminSiteSettingsHidden")}</h2>
        <div className="space-y-0">
          {generalSlice.map(([k, v]) => (
            <div
              key={k}
              className={cn(
                "grid gap-2 border-b border-border/60 py-2 sm:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]",
                isFa && "text-right"
              )}
            >
              <span className="break-all font-mono text-xs text-muted-foreground">{k}</span>
              <span className="min-w-0 break-words text-sm">{v}</span>
            </div>
          ))}
        </div>
        <DataPagination
          meta={generalMeta}
          isFa={isFa}
          onPageChange={setGenPage}
          onPerPageChange={(n) => {
            setGenPer(n)
            setGenPage(1)
          }}
        />
      </div>
    )
  }

  if (activeTab === "bots") {
    return (
      <DashboardBotsAdmin
        settings={settings}
        botsList={asRecordArray(data.botsList)}
        botsPagination={pickPagination(data, "botsList")}
        isFa={isFa}
        resellerSelfServe={false}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("botsList", p)}
        onPerPageChange={(n) => setPer("botsList", n)}
      />
    )
  }

  if (activeTab === "reseller_bots") {
    return (
      <DashboardBotsAdmin
        settings={settings}
        botsList={asRecordArray(data.botsList)}
        botsPagination={pickPagination(data, "botsList")}
        isFa={isFa}
        resellerSelfServe
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("botsList", p)}
        onPerPageChange={(n) => setPer("botsList", n)}
      />
    )
  }

  if (activeTab === "xui_panels") {
    return (
      <DashboardPanelsAdmin
        panels={panels}
        pagination={pickPagination(data, "panels")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("panels", p)}
        onPerPageChange={(n) => setPer("panels", n)}
      />
    )
  }

  if (activeTab === "configs") {
    return (
      <DashboardConfigsAdmin
        panels={panels}
        plans={plans}
        isFa={isFa}
        configsActive={activeTab === "configs"}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "plan_cats") {
    return (
      <DashboardPlanCatsAdmin
        planCategories={planCategories}
        panels={panels}
        pagination={pickPagination(data, "planCategories")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("planCategories", p)}
        onPerPageChange={(n) => setPer("planCategories", n)}
      />
    )
  }

  if (activeTab === "plans") {
    return (
      <DashboardPlansAdmin
        plans={plans}
        panels={panels}
        planCategories={planCategories}
        l2tpServers={l2tp}
        wholesaleLines={wholesaleLinesReseller}
        resellerPlanFloors={asRecordArray((data as Record<string, unknown>).resellerPlanFloors)}
        resellerMode={isReseller}
        actorSvpUserId={isReseller ? dashActorSvpUserId(data) : 0}
        panelAccessDiagnostics={
          isReseller
            ? ((data.resellerPanelAccessDiagnostics ?? null) as ResellerPanelAccessDiagnostics | null)
            : null
        }
        pagination={pickPagination(data, "plans")}
        settings={settings}
        showCatalogDefaultsSave={!isReseller}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("plans", p)}
        onPerPageChange={(n) => setPer("plans", n)}
      />
    )
  }

  if (activeTab === "cards") {
    return (
      <DashboardCardsAdmin
        cards={cards}
        pagination={pickPagination(data, "cards")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("cards", p)}
        onPerPageChange={(n) => setPer("cards", n)}
      />
    )
  }

  if (activeTab === "l2tp_servers") {
    return (
      <DashboardL2tpAdmin
        servers={l2tp}
        pagination={pickPagination(data, "l2tpServers")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("l2tpServers", p)}
        onPerPageChange={(n) => setPer("l2tpServers", n)}
      />
    )
  }

  if (activeTab === "wholesale_lines") {
    return (
      <DashboardWholesaleLinesAdmin
        catalog={wholesaleLinesCatalog}
        panels={panels}
        l2tpServers={l2tp}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "receipts") {
    return (
      <DashboardReceiptsAdmin
        receipts={receipts}
        receiptAggregates={data.receiptAggregates}
        pagination={pickPagination(data, "receipts")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("receipts", p)}
        onPerPageChange={(n) => setPer("receipts", n)}
      />
    )
  }

  if (activeTab === "reseller_finance") {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    const charges = Array.isArray((data as Record<string, unknown>).resellerCustomerCharges)
      ? ((data as Record<string, unknown>).resellerCustomerCharges as Record<string, unknown>[])
      : []
    return (
      <DashboardResellerFinance
        wholesaleLines={wholesaleLinesReseller}
        receipts={receipts}
        customerCharges={charges}
        actorBalance={actorBal}
        isFa={isFa}
        receiptsPagination={pickPagination(data, "receipts")}
        onMutateSuccess={onAdminMutateSuccess}
        onReceiptsPageChange={(p) => setPage("receipts", p)}
        onReceiptsPerPageChange={(n) => setPer("receipts", n)}
      />
    )
  }

  if (activeTab === "broadcast") {
    return (
      <DashboardBroadcastAdmin
        broadcasts={broadcasts}
        broadcastQueueAggregates={data.broadcastQueueAggregates}
        pagination={pickPagination(data, "broadcasts")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("broadcasts", p)}
        onPerPageChange={(n) => setPer("broadcasts", n)}
      />
    )
  }

  if (activeTab === "texts") {
    const textsMeta = pickPagination(data, "texts")
    if (!texts.length && (!textsMeta || textsMeta.total === 0)) {
      return <>{notFound}</>
    }
    const textDefaults =
      data.textDefaults && typeof data.textDefaults === "object"
        ? (data.textDefaults as Record<string, { fa: string; en: string } | string>)
        : {}
    return (
      <DashboardTextsAdmin
        texts={texts}
        textDefaults={textDefaults}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "bot_ui") {
    return (
      <DashboardBotUiStudio
        uiLayout={data.uiLayout as Record<string, unknown> | undefined}
        uiRegistry={data.uiRegistry as Record<string, unknown> | undefined}
        textDefaults={
          data.textDefaults && typeof data.textDefaults === "object"
            ? (data.textDefaults as Record<string, unknown>)
            : undefined
        }
        layoutReadOnly={isReseller}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "users" && userDetailId != null && userDetailId > 0) {
    return (
      <DashboardUserDetailAdmin
        userId={userDetailId}
        plans={plans}
        isFa={isFa}
        isReseller={isReseller}
        onBack={onCloseUserDetail}
        onMutateSuccess={onAdminMutateSuccess}
        onOpenUserDetail={onOpenUserDetail}
      />
    )
  }

  if (activeTab === "users_bulk") {
    return (
      <DashboardUsersBulkAdmin isFa={isFa} onMutateSuccess={onAdminMutateSuccess} canRunBulkWorker={!isReseller} />
    )
  }

  if (activeTab === "users") {
    return (
      <DashboardUsersAdmin
        users={users}
        pending={pending}
        usersPagination={pickPagination(data, "usersList")}
        pendingPagination={pickPagination(data, "pendingUsers")}
        isFa={isFa}
        isReseller={isReseller}
        actorPermissions={
          (data.actorPermissions as Record<string, boolean> | undefined) ?? undefined
        }
        onMutateSuccess={onAdminMutateSuccess}
        onUsersPageChange={(p) => setPage("usersList", p)}
        onUsersPerPageChange={(n) => setPer("usersList", n)}
        onPendingPageChange={(p) => setPage("pendingUsers", p)}
        onPendingPerPageChange={(n) => setPer("pendingUsers", n)}
        onOpenUserDetail={onOpenUserDetail}
        usersSearchQuery={usersSearchQuery}
        onUsersSearchQueryChange={onUsersSearchQueryChange}
      />
    )
  }

  if (activeTab === "resellers") {
    return (
      <DashboardResellersAdmin
        rows={resellers}
        panels={panels}
        wholesaleLinesCatalog={wholesaleLinesCatalog}
        resellerWholesaleLineIdsMap={
          (data.resellerWholesaleLineIdsMap as Record<string, number[]> | undefined) ?? {}
        }
        resellerPermissionsMap={(data.resellerPermissionsMap as Record<string, Record<string, boolean>>) || {}}
        resellerPanelPricesMap={(data.resellerPanelPricesMap as Record<string, Array<{ panel_id?: number; price_per_gb?: number | string; panel_access?: boolean | number }>>) || {}}
        pagination={pickPagination(data, "resellers")}
        canManageResellerControls={!isReseller}
        canManagePanelPrices={true}
        isFa={isFa}
        onPageChange={(p) => setPage("resellers", p)}
        onPerPageChange={(n) => setPer("resellers", n)}
        onOpenUserDetail={onOpenUserDetail}
        onOpenWorkspace={onOpenResellerWorkspace}
        onMutateSuccess={onAdminMutateSuccess}
        onImpersonateReseller={onImpersonateReseller}
      />
    )
  }

  if (activeTab === "reseller_workspace") {
    const ctxId = Number((data as Record<string, unknown>).resellerContextId ?? 0)
    if (ctxId > 0) {
      return (
        <DashboardUserDetailAdmin
          userId={ctxId}
          plans={plans}
          isFa={isFa}
          isReseller={isReseller}
          onBack={() => onSelectTab("resellers")}
          onMutateSuccess={onAdminMutateSuccess}
          onOpenUserDetail={onOpenUserDetail}
        />
      )
    }
  }

  if (activeTab === "backup") {
    return <DashboardBackupAdmin settings={settings} isFa={isFa} onMutateSuccess={onAdminMutateSuccess} />
  }

  if (activeTab === "notifications") {
    return <DashboardNotificationsAdmin settings={settings} isFa={isFa} onMutateSuccess={onAdminMutateSuccess} />
  }

  if (activeTab === "referral") {
    return (
      <DashboardReferralAdmin
        settings={settings}
        referralStats={data.referralStats}
        referralEvents={asRecordArray(data.referralEvents)}
        eventsPagination={pickPagination(data, "referralEvents")}
        readOnlySettings={isReseller}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onEventsPageChange={(p) => setPage("referralEvents", p)}
        onEventsPerPageChange={(n) => setPer("referralEvents", n)}
      />
    )
  }

  if (activeTab === "logs") {
    return <DashboardLogsAdmin isFa={isFa} />
  }

  if (activeTab === "discounts") {
    return (
      <DashboardDiscountsAdmin
        discountCodes={disc}
        pagination={pickPagination(data, "discountCodes")}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("discountCodes", p)}
        onPerPageChange={(n) => setPer("discountCodes", n)}
      />
    )
  }

  return <>{notFound}</>
}

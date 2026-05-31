import { useCallback, useEffect, useMemo, type Dispatch, type SetStateAction } from "react"
import { useTranslation } from "react-i18next"
import { DashboardAuditAdmin } from "@/components/dashboard-audit-admin"
import { DashboardBackupAdmin } from "@/components/dashboard-backup-admin"
import { DashboardBotUiStudio } from "@/components/dashboard-bot-ui-studio"
import { DashboardBotsAdmin } from "@/components/dashboard-bots-admin"
import { DashboardBroadcastAdmin } from "@/components/dashboard-broadcast-admin"
import { DashboardCardsAdmin } from "@/components/dashboard-cards-admin"
import { DashboardDiscountsAdmin } from "@/components/dashboard-discounts-admin"
import { DashboardSiteSettingsAdmin } from "@/components/dashboard-site-settings-admin"
import { DashboardMonitoring } from "@/components/dashboard-monitoring"
import { DashboardOverview, type OverviewPayload } from "@/components/dashboard-overview"
import { DashboardConfigsAdmin } from "@/components/dashboard-configs-admin"
import { DashboardPanelsAdmin } from "@/components/dashboard-panels-admin"
import { DashboardPlanCatsAdmin } from "@/components/dashboard-plan-cats-admin"
import { DashboardPlansAdmin, type ResellerPanelAccessDiagnostics } from "@/components/dashboard-plans-admin"
import {
  DashboardReceiptsAdmin,
  type ReceiptsListFilters,
} from "@/components/dashboard-receipts-admin"
import { DashboardResellerChargeAdmin } from "@/components/dashboard-reseller-charge-admin"
import { DashboardReferralAdmin } from "@/components/dashboard-referral-admin"
import { DashboardResellerReportsPlaceholder } from "@/components/dashboard-reseller-reports-placeholder"
import { DashboardResellersAdmin } from "@/components/dashboard-resellers-admin"
import { DashboardTextsAdmin } from "@/components/dashboard-texts-admin"
import { DashboardUsersAdmin } from "@/components/dashboard-users-admin"
import { DashboardUsersBulkAdmin } from "@/components/dashboard-users-bulk-admin"
import { DashboardUserDetailAdmin } from "@/components/dashboard-user-detail-admin"
import {
  listQuerySetPage,
  parsePaginationMeta,
  type ListQueryKey,
  type PaginationMeta,
} from "@/lib/dash-pagination"
import { DashboardResellerPanelsAdmin } from "@/components/dashboard-reseller-panels-admin"

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
  resellersSearchQuery: string
  resellersStatusFilter: string
  onResellersFiltersChange: (patch: { q?: string; status?: string }) => void
  receiptsListFilters: ReceiptsListFilters
  onReceiptsListFiltersChange: (patch: Partial<ReceiptsListFilters>) => void
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
  resellersSearchQuery,
  resellersStatusFilter,
  onResellersFiltersChange,
  receiptsListFilters,
  onReceiptsListFiltersChange,
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
  const l2tpEnabled = useMemo(() => {
    const f = settings?.features
    return !!(f && typeof f === "object" && (f as Record<string, unknown>).l2tp === true)
  }, [settings?.features])
  useEffect(() => {
    if (activeTab === "l2tp_servers" && !l2tpEnabled) {
      onSelectTab("dashboard")
    }
  }, [activeTab, l2tpEnabled, onSelectTab])
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
  const discountUsageSummary = (data.discountUsageSummary as Record<string, unknown> | undefined) ?? null
  const broadcasts = asRecordArray(data.broadcasts)
  const resellers = asRecordArray(data.resellers)
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
        compactHealthOnly={false}
        prependResellerFinance={isReseller}
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
        compactHealthOnly={false}
      />
    )
  }

  if (activeTab === "site_settings") {
    const wpPagesRaw = data.wpPages
    const wpPages = Array.isArray(wpPagesRaw)
      ? (wpPagesRaw as { id?: unknown; title?: unknown }[])
          .map((p) => ({ id: Number(p.id), title: String(p.title ?? "") }))
          .filter((p) => Number.isFinite(p.id) && p.id > 0)
      : []
    const permMap =
      data.resellerPermissionsMap && typeof data.resellerPermissionsMap === "object"
        ? (data.resellerPermissionsMap as Record<string, Record<string, boolean>>)
        : {}
    return (
      <DashboardSiteSettingsAdmin
        settings={settings}
        wpPages={wpPages}
        plans={plans}
        resellers={resellers}
        resellerPermissionsMap={permMap}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "bots") {
    return (
      <DashboardBotsAdmin
        settings={settings}
        botsList={[]}
        botsPagination={null}
        isFa={isFa}
        variant="site"
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
        variant={isReseller ? "reseller_self" : "reseller_admin"}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("botsList", p)}
        onPerPageChange={(n) => setPer("botsList", n)}
      />
    )
  }

  if (activeTab === "reseller_xui_panels") {
    return (
      <DashboardResellerPanelsAdmin
        panels={panels}
        resellerPanelPricesMap={
          (data.resellerPanelPricesMap as Record<string, Array<Record<string, unknown>> | undefined>) ?? {}
        }
        isFa={isFa}
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
        resellerChoices={resellers}
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
        settings={settings}
        canEditDisplayMode={!isReseller}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("cards", p)}
        onPerPageChange={(n) => setPer("cards", n)}
      />
    )
  }

  if (activeTab === "reseller_charge" && isReseller) {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    const charges = Array.isArray((data as Record<string, unknown>).resellerCustomerCharges)
      ? ((data as Record<string, unknown>).resellerCustomerCharges as Record<string, unknown>[])
      : []
    return (
      <DashboardResellerChargeAdmin
        receipts={receipts}
        actorBalance={actorBal}
        customerCharges={charges}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "receipts") {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    const charges = Array.isArray((data as Record<string, unknown>).resellerCustomerCharges)
      ? ((data as Record<string, unknown>).resellerCustomerCharges as Record<string, unknown>[])
      : []
    const perms = (data.actorPermissions as Record<string, boolean> | undefined) ?? {}
    const canReviewReceipts = !isReseller || perms["receipts.review"] === true
    return (
      <DashboardReceiptsAdmin
        receipts={receipts}
        receiptAggregates={data.receiptAggregates}
        settings={settings}
        pagination={pickPagination(data, "receipts")}
        isFa={isFa}
        isReseller={isReseller}
        canReviewReceipts={canReviewReceipts}
        actorBalance={actorBal}
        customerCharges={charges}
        listFilters={receiptsListFilters}
        onListFiltersChange={onReceiptsListFiltersChange}
        dashboardBaseUrl={dashboardBaseUrl}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("receipts", p)}
        onPerPageChange={(n) => setPer("receipts", n)}
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
        planCategories={planCategories}
        settings={settings}
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
      <DashboardUsersBulkAdmin
        panels={panels}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
        canRunBulkWorker={!isReseller}
      />
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
        resellerPermissionsMap={(data.resellerPermissionsMap as Record<string, Record<string, boolean>>) || {}}
        resellersSearchQuery={resellersSearchQuery}
        resellersStatusFilter={resellersStatusFilter}
        onResellersFiltersChange={onResellersFiltersChange}
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
          planCategories={planCategories}
          settings={settings}
          isFa={isFa}
          isReseller={isReseller}
          onBack={() => onSelectTab("resellers")}
          onMutateSuccess={onAdminMutateSuccess}
          onOpenUserDetail={onOpenUserDetail}
        />
      )
    }
  }

  if (activeTab === "audit") {
    return <DashboardAuditAdmin isFa={isFa} />
  }

  if (activeTab === "backup") {
    return <DashboardBackupAdmin settings={settings} isFa={isFa} onMutateSuccess={onAdminMutateSuccess} />
  }

  if (activeTab === "referral") {
    return (
      <DashboardReferralAdmin
        mode="settings"
        settings={settings}
        referralStats={null}
        referralEvents={[]}
        eventsPagination={null}
        readOnlySettings={isReseller}
        isFa={isFa}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "referral_reports") {
    return (
      <DashboardReferralAdmin
        mode="reports"
        settings={settings}
        referralStats={data.referralStats}
        referralEvents={asRecordArray(data.referralEvents)}
        eventsPagination={pickPagination(data, "referralEvents")}
        isFa={isFa}
        onEventsPageChange={(p) => setPage("referralEvents", p)}
        onEventsPerPageChange={(n) => setPer("referralEvents", n)}
      />
    )
  }

  if (activeTab === "reseller_reports") {
    return <DashboardResellerReportsPlaceholder isFa={isFa} />
  }

  if (activeTab === "discounts") {
    return (
      <DashboardDiscountsAdmin
        discountCodes={disc}
        discountUsageSummary={discountUsageSummary}
        plans={plans}
        usersList={users}
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

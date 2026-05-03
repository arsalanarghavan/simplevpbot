"use client"

import { Check, LayoutDashboard, LifeBuoy, MessageSquareQuote, UserRoundCog } from "lucide-react"
import { useTranslation } from "react-i18next"

import { NavGrouped } from "@/components/nav-grouped"
import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import { SidebarSearch } from "@/components/sidebar-search"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type NavTab = {
  key: string
  label: string
}

const menuBtnCollapsedIcon =
  "group-data-[collapsible=icon]:justify-center [&>span]:group-data-[collapsible=icon]:hidden"

function SidebarQuickLinks({ rtl }: { rtl: boolean }) {
  const { t } = useTranslation()
  const host =
    typeof window !== "undefined" ? window.location.hostname : "localhost"
  const supportHref = `mailto:support@${host}?subject=${encodeURIComponent(
    t("sidebar.footer.support")
  )}`

  return (
    <SidebarMenu
      className="border-b border-sidebar-border px-0 pb-3"
      dir={rtl ? "rtl" : undefined}
    >
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild
          size="default"
          tooltip={t("sidebar.footer.support")}
          className={menuBtnCollapsedIcon}
        >
          <a href={supportHref}>
            <LifeBuoy />
            <span className="truncate">{t("sidebar.footer.support")}</span>
          </a>
        </SidebarMenuButton>
      </SidebarMenuItem>
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild
          size="default"
          tooltip={t("sidebar.footer.feedback")}
          className={menuBtnCollapsedIcon}
        >
          <a href="#feedback" onClick={(e) => e.preventDefault()}>
            <MessageSquareQuote />
            <span className="truncate">{t("sidebar.footer.feedback")}</span>
          </a>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

function RoleSwitcher({ isAdmin }: { isAdmin: boolean }) {
  const { t } = useTranslation()
  const label = isAdmin ? t("sidebar.role.admin") : t("sidebar.role.user")
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8 shrink-0 group-data-[collapsible=icon]:hidden"
          aria-label={label}
          title={label}
        >
          <UserRoundCog className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-48">
        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
          {t("sidebar.role.switchLabel")}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem disabled={!isAdmin} className="gap-2 text-sm">
          {isAdmin ? (
            <Check className="size-4 shrink-0 opacity-90" />
          ) : (
            <span className="inline-block w-4 shrink-0" aria-hidden />
          )}
          {t("sidebar.role.admin")}
        </DropdownMenuItem>
        <DropdownMenuItem
          disabled={isAdmin}
          className="gap-2 text-sm"
          title={isAdmin ? t("sidebar.role.switchHint") : undefined}
        >
          {!isAdmin ? (
            <Check className="size-4 shrink-0 opacity-90" />
          ) : (
            <span className="inline-block w-4 shrink-0" aria-hidden />
          )}
          {t("sidebar.role.user")}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export function AppSidebar({
  side,
  variant,
  navTabs,
  user,
  activeTabKey,
  onSelectTab,
  dashboardBaseUrl,
  siteName,
  siteIconUrl,
  onOpenUserDetail,
  userSearchRestUrl,
  userSearchNonce,
}: {
  side: "left" | "right"
  variant: "admin" | "user"
  navTabs: NavTab[]
  user: { name: string; email: string; avatar: string; logoutUrl?: string }
  activeTabKey: string
  onSelectTab: (tabKey: string) => void
  dashboardBaseUrl: string
  siteName: string
  siteIconUrl?: string
  onOpenUserDetail?: (svpUserId: number) => void
  userSearchRestUrl?: string
  userSearchNonce?: string
}) {
  const isFa = side === "right"
  const { t } = useTranslation()
  const base = dashboardBaseUrl.replace(/\/?$/, "")
  const subItemUrl = (tabKey: string) => `${base}/${encodeURIComponent(tabKey)}/`

  const displayName = siteName.trim() || t("sidebar.siteFallback")
  const isAdmin = variant === "admin"

  const userMainItems = [
    {
      title: t("myPanel"),
      url: `${base}/`,
      icon: LayoutDashboard,
      isActive: true,
      items: navTabs.map((tab) => ({
        title: tab.label,
        url: subItemUrl(tab.key),
        tabKey: tab.key,
      })),
    },
  ]

  return (
    <Sidebar side={side} collapsible="icon">
      {variant === "admin" && (
        <SidebarHeader
          className={cn(
            "gap-2 border-b border-sidebar-border pb-3",
            isFa && "text-right"
          )}
        >
          {isFa ? (
            <div dir="rtl" className="flex items-center gap-2 px-2 pt-2">
              {siteIconUrl ? (
                <img
                  src={siteIconUrl}
                  alt=""
                  className="size-8 shrink-0 rounded-md object-cover"
                />
              ) : null}
              <p className="min-w-0 flex-1 truncate text-sm font-semibold leading-tight">
                {displayName}
              </p>
              <RoleSwitcher isAdmin={isAdmin} />
            </div>
          ) : (
            <div className="flex items-center gap-2 px-2 pt-2">
              {siteIconUrl ? (
                <img
                  src={siteIconUrl}
                  alt=""
                  className="size-8 shrink-0 rounded-md object-cover"
                />
              ) : null}
              <div className="flex min-w-0 flex-1 items-center gap-2">
                <p className="min-w-0 flex-1 truncate text-sm font-semibold leading-tight">
                  {displayName}
                </p>
                <RoleSwitcher isAdmin={isAdmin} />
              </div>
            </div>
          )}
          <div className="px-2">
            <SidebarSearch
              onSelectTab={onSelectTab}
              onOpenUserDetail={variant === "admin" ? onOpenUserDetail : undefined}
              restUrl={variant === "admin" ? userSearchRestUrl : undefined}
              nonce={variant === "admin" ? userSearchNonce : undefined}
              rtl={isFa}
            />
          </div>
        </SidebarHeader>
      )}
      <SidebarContent>
        {variant === "admin" ? (
          <NavGrouped
            activeTabKey={activeTabKey}
            onSelectTab={onSelectTab}
            subItemUrl={subItemUrl}
            rtl={isFa}
          />
        ) : (
          <NavMain
            items={userMainItems}
            groupLabel={t("sidebar.sections.overview")}
            activeTabKey={activeTabKey}
            onSubItemClick={onSelectTab}
            subItemUrl={subItemUrl}
            rtl={isFa}
          />
        )}
      </SidebarContent>
      <SidebarFooter className="gap-0">
        {variant === "admin" && <SidebarQuickLinks rtl={isFa} />}
        <div className={cn(variant === "admin" && "px-0 pt-1")}>
          <NavUser user={user} rtl={isFa} />
        </div>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}

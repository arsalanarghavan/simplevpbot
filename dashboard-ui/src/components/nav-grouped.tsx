"use client"

import { ChevronRight } from "lucide-react"
import { useTranslation } from "react-i18next"

import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from "@/components/ui/sidebar"
import type { AdminNavSection } from "@/config/admin-nav"
import { ADMIN_NAV_SECTIONS } from "@/config/admin-nav"
import { cn } from "@/lib/utils"

function collapsibleOpen(
  entry: AdminNavSection["entries"][number],
  activeTabKey: string
): boolean {
  if (entry.kind === "leaf") return entry.tabKey === activeTabKey
  return entry.children.some((c) => c.tabKey === activeTabKey)
}

export function NavGrouped({
  activeTabKey,
  onSelectTab,
  subItemUrl,
  rtl = false,
  sections = ADMIN_NAV_SECTIONS,
}: {
  activeTabKey: string
  onSelectTab: (tabKey: string) => void
  subItemUrl: (tabKey: string) => string
  rtl?: boolean
  sections?: AdminNavSection[]
}) {
  const { t } = useTranslation()

  const itemLabel = (tabKey: string) =>
    t(`sidebar.items.${tabKey}`, { defaultValue: tabKey })

  return (
    <>
      {sections.map((section) => (
        <SidebarGroup
          key={section.id}
          className={cn(rtl && "text-right")}
          dir={rtl ? "rtl" : undefined}
        >
          <SidebarGroupLabel>{t(section.hintKey)}</SidebarGroupLabel>
          <SidebarMenu>
            {section.entries.map((entry) => {
              if (entry.kind === "leaf") {
                const href = subItemUrl(entry.tabKey)
                const isActive = activeTabKey === entry.tabKey
                const Icon = entry.icon
                return (
                  <SidebarMenuItem key={entry.tabKey}>
                    <SidebarMenuButton asChild isActive={isActive} tooltip={itemLabel(entry.tabKey)}>
                      <a
                        href={href}
                        onClick={(e) => {
                          e.preventDefault()
                          onSelectTab(entry.tabKey)
                        }}
                      >
                        <Icon />
                        <span>{itemLabel(entry.tabKey)}</span>
                      </a>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                )
              }

              const open = collapsibleOpen(entry, activeTabKey)
              const ParentIcon = entry.icon
              return (
                <Collapsible
                  key={entry.id}
                  asChild
                  defaultOpen={open}
                  className="group/collapsible"
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton tooltip={t(entry.labelKey)}>
                        <ParentIcon />
                        <span>{t(entry.labelKey)}</span>
                        <ChevronRight
                          className={cn(
                            "ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90",
                            rtl && "-scale-x-100"
                          )}
                        />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub
                        className={cn(
                          rtl &&
                            "me-3.5 ms-0 translate-x-0 border-r border-l-0 pe-2.5 ps-0"
                        )}
                      >
                        {entry.children.map((sub) => {
                          const href = subItemUrl(sub.tabKey)
                          const isActive = activeTabKey === sub.tabKey
                          const SubIcon = sub.icon
                          return (
                            <SidebarMenuSubItem key={sub.tabKey}>
                              <SidebarMenuSubButton asChild isActive={isActive}>
                                <a
                                  href={href}
                                  className={cn(SubIcon && "gap-2")}
                                  onClick={(e) => {
                                    e.preventDefault()
                                    onSelectTab(sub.tabKey)
                                  }}
                                >
                                  {SubIcon ? <SubIcon className="size-4 shrink-0" /> : null}
                                  <span>{itemLabel(sub.tabKey)}</span>
                                </a>
                              </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                          )
                        })}
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>
              )
            })}
          </SidebarMenu>
        </SidebarGroup>
      ))}
    </>
  )
}

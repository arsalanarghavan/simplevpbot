import { ChevronRight, type LucideIcon } from "lucide-react"

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
import { cn } from "@/lib/utils"

export function NavMain({
  items,
  groupLabel = "Menu",
  activeTabKey,
  onSubItemClick,
  subItemUrl,
  rtl = false,
}: {
  items: {
    title: string
    url: string
    icon?: LucideIcon
    isActive?: boolean
    items?: {
      title: string
      url: string
      tabKey: string
    }[]
  }[]
  groupLabel?: string
  /** When set, sub-items use client navigation + highlight. */
  activeTabKey?: string
  onSubItemClick?: (tabKey: string) => void
  /** Build href for sub-items (e.g. open in new tab). */
  subItemUrl?: (tabKey: string) => string
  /** Persian / right sidebar: RTL alignment for labels and items. */
  rtl?: boolean
}) {
  return (
    <SidebarGroup className={cn(rtl && "text-right")} dir={rtl ? "rtl" : undefined}>
      <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => (
          <Collapsible
            key={item.title}
            asChild
            defaultOpen={item.isActive}
            className="group/collapsible"
          >
            <SidebarMenuItem>
              <CollapsibleTrigger asChild>
                <SidebarMenuButton tooltip={item.title}>
                  {item.icon && <item.icon />}
                  <span>{item.title}</span>
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
                  {item.items?.map((subItem) => {
                    const href = subItemUrl ? subItemUrl(subItem.tabKey) : subItem.url
                    const isActive = activeTabKey === subItem.tabKey
                    return (
                    <SidebarMenuSubItem key={subItem.tabKey}>
                      <SidebarMenuSubButton asChild isActive={isActive}>
                        <a
                          href={href}
                          onClick={(e) => {
                            if (onSubItemClick) {
                              e.preventDefault()
                              onSubItemClick(subItem.tabKey)
                            }
                          }}
                        >
                          <span>{subItem.title}</span>
                        </a>
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    )
                  })}
                </SidebarMenuSub>
              </CollapsibleContent>
            </SidebarMenuItem>
          </Collapsible>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}

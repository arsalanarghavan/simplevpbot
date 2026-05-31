import { ChevronsUpDown, LogOut, MessagesSquare } from "lucide-react"
import { useTranslation } from "react-i18next"

import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { formatPlainLatinInt } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

function IconTelegram({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={className}
      aria-hidden
      focusable="false"
    >
      <path
        fill="currentColor"
        d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12 12 12 0 0 0-12-12 12 12 0 0 0-12 12zm4.962 7.224-.156-.634c-.203-.828-.637-.988-1.293-.813l-9.31 2.863c-.852.262-.854.826-.156 1.042l2.384.746 5.532-3.492c.262-.165.502-.076.306.104l-4.483 4.047-.172 2.017c.315 0 .454-.144.63-.315l1.52-1.476 3.15 2.322c.583.32 1.003.154 1.147-.534z"
      />
    </svg>
  )
}

function MessengerIds({
  tgUserId,
  baleUserId,
  rtl,
}: {
  tgUserId: number
  baleUserId: number
  rtl: boolean
}) {
  const showTg = tgUserId > 0
  const showBl = baleUserId > 0
  if (!showTg && !showBl) return null
  return (
    <div
      className={cn(
        "flex flex-col gap-0.5 text-xs text-muted-foreground",
        rtl ? "items-end" : "items-start"
      )}
    >
      {showTg ? (
        <div
          className={cn(
            "flex max-w-full items-center gap-1.5",
          )}
          dir="ltr"
        >
          <IconTelegram className="size-3.5 shrink-0 text-sky-500" />
          <span className="truncate tabular-nums font-mono">
            {formatPlainLatinInt(tgUserId)}
          </span>
        </div>
      ) : null}
      {showBl ? (
        <div
          className={cn(
            "flex max-w-full items-center gap-1.5",
          )}
          dir="ltr"
        >
          <MessagesSquare
            className="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
            aria-hidden
          />
          <span className="truncate tabular-nums font-mono">
            {formatPlainLatinInt(baleUserId)}
          </span>
        </div>
      ) : null}
    </div>
  )
}

export function NavUser({
  user,
  rtl = false,
}: {
  user: {
    name: string
    tgUserId: number
    baleUserId: number
    avatar: string
    logoutUrl?: string
  }
  rtl?: boolean
}) {
  const { isMobile } = useSidebar()
  const { t } = useTranslation()

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              dir={rtl ? "rtl" : undefined}
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="h-8 w-8 shrink-0 rounded-lg">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-lg">U</AvatarFallback>
              </Avatar>
              <div
                className={cn(
                  "grid min-w-0 flex-1 gap-0.5 text-sm leading-tight",
                  rtl ? "text-end" : "text-start"
                )}
              >
                <span className="truncate font-medium">{user.name}</span>
                <MessengerIds
                  tgUserId={user.tgUserId}
                  baleUserId={user.baleUserId}
                  rtl={rtl}
                />
              </div>
              <ChevronsUpDown
                className={cn(
                  "size-4 shrink-0 opacity-70",
                  rtl ? "me-auto ms-0 rotate-180" : "ms-auto"
                )}
              />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className={cn(
              "w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg",
              rtl && "text-right"
            )}
            style={{ direction: rtl ? "rtl" : "ltr" }}
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="p-0 font-normal">
              <div
                className={cn(
                  "flex items-start gap-2 px-1 py-1.5 text-sm",
                  rtl ? "text-end" : "text-start"
                )}
              >
                <Avatar className="h-8 w-8 shrink-0 rounded-lg">
                  <AvatarImage src={user.avatar} alt={user.name} />
                  <AvatarFallback className="rounded-lg">U</AvatarFallback>
                </Avatar>
                <div
                  className={cn(
                    "grid min-w-0 flex-1 gap-1 text-sm leading-tight",
                    rtl ? "text-end" : "text-start"
                  )}
                >
                  <span className="truncate font-medium">{user.name}</span>
                  <MessengerIds
                    tgUserId={user.tgUserId}
                    baleUserId={user.baleUserId}
                    rtl={rtl}
                  />
                </div>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <a
                href={user.logoutUrl || "#"}
                className={cn("gap-2", rtl && "justify-end")}
              >
                <LogOut />
                {t("sidebar.user.logout")}
              </a>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

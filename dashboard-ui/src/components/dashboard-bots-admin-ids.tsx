"use client"

import { useState } from "react"
import { Plus, X } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { dashDir } from "@/lib/dash-locale"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatPlainLatinInt } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type Platform = "telegram" | "bale"

export function AdminIdChips({
  platform,
  label,
  ids,
  resellerId = 0,
  isFa,
  busy,
  tp,
  onChanged,
  onError,
}: {
  platform: Platform
  label: string
  ids: number[]
  resellerId?: number
  isFa: boolean
  busy: boolean
  tp: (k: string) => string
  onChanged: () => void
  onError: (msg: string) => void
}) {
  const [addOpen, setAddOpen] = useState(false)
  const [chatId, setChatId] = useState("")
  const [localBusy, setLocalBusy] = useState(false)

  const run = async (op: "bot_admin_id_add" | "bot_admin_id_remove", cid: number) => {
    setLocalBusy(true)
    try {
      const res = await postAdminMutate(op, {
        platform,
        chat_id: cid,
        reseller_svp_user_id: resellerId,
      })
      if (!res.ok) {
        onError(res.message || tp("saveError"))
        return false
      }
      onChanged()
      return true
    } finally {
      setLocalBusy(false)
    }
  }

  const disabled = busy || localBusy

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label className="text-xs font-medium text-muted-foreground">{label}</Label>
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="h-7 gap-1 text-xs"
          disabled={disabled}
          onClick={() => {
            setChatId("")
            setAddOpen(true)
          }}
        >
          <Plus className="size-3.5" />
          {tp("adminIdAdd")}
        </Button>
      </div>
      <div className={cn("flex flex-wrap gap-1.5")}>
        {ids.length === 0 ? (
          <span className="text-xs text-muted-foreground">{tp("adminIdEmpty")}</span>
        ) : (
          ids.map((id) => (
            <Badge key={id} variant="secondary" className="gap-1 font-mono text-xs" dir="ltr">
              {formatPlainLatinInt(id)}
              <button
                type="button"
                className="rounded-sm opacity-70 hover:opacity-100"
                disabled={disabled}
                aria-label={tp("adminIdRemove")}
                onClick={() => void run("bot_admin_id_remove", id)}
              >
                <X className="size-3" />
              </button>
            </Badge>
          ))
        )}
      </div>

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent className={cn("sm:max-w-sm", isFa && "text-right")} dir={dashDir(isFa)}>
          <DialogHeader>
            <DialogTitle>{tp("adminIdAddTitle")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor={`admin-id-${platform}-${resellerId}`}>{label}</Label>
            <Input
              id={`admin-id-${platform}-${resellerId}`}
              dir="ltr"
              type="number"
              min={1}
              className="font-mono"
              placeholder={tp("adminIdPlaceholder")}
              value={chatId}
              onChange={(e) => setChatId(e.target.value)}
              disabled={disabled}
            />
          </div>
          <DialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setAddOpen(false)} disabled={disabled}>
              {tp("adminIdCancel")}
            </Button>
            <Button
              type="button"
              disabled={disabled}
              onClick={() => {
                const n = parseInt(chatId.trim(), 10)
                if (!Number.isFinite(n) || n < 1) return
                void run("bot_admin_id_add", n).then((ok) => {
                  if (ok) setAddOpen(false)
                })
              }}
            >
              {tp("adminIdAdd")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

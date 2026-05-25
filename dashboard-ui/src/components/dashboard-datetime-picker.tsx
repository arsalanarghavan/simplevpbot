"use client"

import { GregorianDateTimePicker } from "@/components/dashboard-date-picker/gregorian-datetime-picker"
import { JalaliDateTimePicker } from "@/components/dashboard-date-picker/jalali-datetime-picker"
import { cn } from "@/lib/utils"

export { apiDatetimeToMs, msToApiDatetime } from "@/lib/datetime-api"

export function DashboardDateTimePicker({
  value,
  onChange,
  isFa,
  label,
  className,
}: {
  value: string
  onChange: (apiValue: string) => void
  isFa: boolean
  label?: string
  className?: string
}) {
  const Picker = isFa ? JalaliDateTimePicker : GregorianDateTimePicker
  return (
    <Picker
      value={value}
      onChange={onChange}
      label={label}
      className={cn(className)}
    />
  )
}

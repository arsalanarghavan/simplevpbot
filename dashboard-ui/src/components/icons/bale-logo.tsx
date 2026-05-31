import { cn } from "@/lib/utils"

export function BaleLogo({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      className={cn("size-4", className)}
      aria-hidden
    >
      <circle cx="12" cy="12" r="12" fill="#00C853" />
      <path
        fill="#fff"
        d="M7.2 7.5h9.6c.55 0 1 .45 1 1v7c0 .55-.45 1-1 1h-3.2l-2.4 2.1c-.35.3-.9.05-.9-.4v-1.7H7.2c-.55 0-1-.45-1-1v-7c0-.55.45-1 1-1zm1.3 2.2v5.6h1.8V9.7H8.5zm3.2 0v5.6h1.8V9.7h-1.8zm3.2 0v5.6h1.8V9.7h-1.8z"
      />
    </svg>
  )
}

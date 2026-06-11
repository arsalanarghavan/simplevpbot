import { readFileSync, existsSync } from "node:fs"
import { resolve } from "node:path"

function loadDotEnv(): void {
  const p = resolve(process.cwd(), ".env")
  if (!existsSync(p)) return
  const raw = readFileSync(p, "utf8")
  for (const line of raw.split("\n")) {
    const t = line.trim()
    if (!t || t.startsWith("#")) continue
    const i = t.indexOf("=")
    if (i < 1) continue
    const k = t.slice(0, i).trim()
    let v = t.slice(i + 1).trim()
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
      v = v.slice(1, -1)
    }
    if (!(k in process.env)) process.env[k] = v
  }
}

loadDotEnv()

const dataDir = resolve(process.cwd(), process.env.DATA_DIR || "./data")

export const env = {
  port: Number(process.env.PORT || 8787),
  /** Per-tenant WP secrets are stored in tenant files; this is master/legacy fallback. */
  sharedSecret: String(process.env.RELAY_SHARED_SECRET || "").trim(),
  masterSecret: String(process.env.RELAY_MASTER_SECRET || process.env.RELAY_SHARED_SECRET || "").trim(),
  dataDir,
  configPath: resolve(process.cwd(), process.env.CONFIG_PATH || joinRel(dataDir, "config.json")),
  tenantsDir: resolve(process.cwd(), process.env.TENANTS_DIR || joinRel(dataDir, "tenants")),
  allowedWpIps: String(process.env.ALLOWED_WP_IPS || "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean),
  telegramApiBase: String(process.env.TELEGRAM_API_BASE || "https://api.telegram.org").replace(/\/$/, ""),
  forwardMaxRetries: Math.max(1, Number(process.env.FORWARD_MAX_RETRIES || 3)),
  forwardTimeoutMs: Math.max(3000, Number(process.env.FORWARD_TIMEOUT_MS || 25000)),
  nginxConfigPath: String(process.env.NGINX_CONFIG_PATH || "/etc/nginx/sites-available/svp-relay.conf"),
}

function joinRel(base: string, rel: string): string {
  return resolve(base, rel)
}

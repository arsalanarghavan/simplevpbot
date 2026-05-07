import fs from "node:fs"
import path from "node:path"
import { fileURLToPath } from "node:url"
import { buildDashboardResources } from "../shared/locales/dashboard.ts"

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = path.resolve(__dirname, "..")

const r = buildDashboardResources()

fs.writeFileSync(
  path.join(repoRoot, "shared/locales/en.json"),
  `${JSON.stringify(r.en.translation, null, 2)}\n`,
)
fs.writeFileSync(
  path.join(repoRoot, "shared/locales/fa.json"),
  `${JSON.stringify(r.fa.translation, null, 2)}\n`,
)

console.log("Wrote shared/locales/en.json and shared/locales/fa.json")

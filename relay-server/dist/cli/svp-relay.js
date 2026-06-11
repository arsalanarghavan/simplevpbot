#!/usr/bin/env node
import { execSync, spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { addDomainToTenant, collectAllDomains, clearConfigCache, listTenants, migrateLegacyConfigIfNeeded, removeDomainFromTenant, tenantSummary, getTenantById, } from "../store.js";
import { env } from "../env.js";
import { renderNginxConfig } from "./nginx.js";
import { issueSslAcme, issueSslCertbot, renewSsl } from "./ssl.js";
const argv = process.argv.slice(2);
const cmd = argv[0] || "help";
const sub = argv[1];
function flag(name) {
    const i = argv.indexOf(name);
    return i >= 0 ? argv[i + 1] : undefined;
}
function pkgRoot() {
    return resolve(dirname(fileURLToPath(import.meta.url)), "../..");
}
function runInstall() {
    const script = resolve(pkgRoot(), "scripts/install.sh");
    if (!existsSync(script)) {
        console.error("install.sh not found");
        process.exit(1);
    }
    execSync(`bash ${JSON.stringify(script)} ${argv.slice(1).join(" ")}`, { stdio: "inherit", shell: "/bin/bash" });
}
function cmdStatus() {
    migrateLegacyConfigIfNeeded();
    clearConfigCache();
    const tenants = listTenants();
    console.log(JSON.stringify({
        ok: true,
        port: env.port,
        uptime_sec: Math.floor(process.uptime()),
        tenant_count: tenants.length,
        domains: collectAllDomains(),
        tenants: tenants.map(tenantSummary),
    }, null, 2));
}
function cmdTenantsList() {
    migrateLegacyConfigIfNeeded();
    for (const t of listTenants()) {
        const s = tenantSummary(t);
        console.log(`${s.tenant_id}\t${s.wp_base_url}\t${s.domains.join(",")}`);
    }
}
function cmdTenantShow(id) {
    const t = getTenantById(id);
    if (!t) {
        console.error("tenant not found");
        process.exit(1);
    }
    const { shared_secret: _s, ...safe } = t;
    console.log(JSON.stringify(safe, null, 2));
}
function cmdDomainsList() {
    console.log(collectAllDomains().join("\n"));
}
function cmdDomainAdd(domain) {
    const tenantId = flag("--tenant") || listTenants()[0]?.tenant_id;
    if (!tenantId) {
        console.error("no tenant — sync config from WordPress first");
        process.exit(1);
    }
    const t = addDomainToTenant(tenantId, domain);
    console.log(JSON.stringify({ ok: true, tenant_id: tenantId, domains: t?.domains }));
}
function cmdDomainRemove(domain) {
    const tenantId = flag("--tenant") || listTenants()[0]?.tenant_id;
    if (!tenantId)
        process.exit(1);
    const t = removeDomainFromTenant(tenantId, domain);
    console.log(JSON.stringify({ ok: true, domains: t?.domains }));
}
function cmdNginxRender() {
    const out = renderNginxConfig({ outPath: flag("--out") });
    console.log(`wrote ${out}`);
}
function cmdSslIssue(domain) {
    const method = (flag("--method") || "certbot");
    const email = flag("--email") || "";
    if (method === "acme")
        issueSslAcme(domain, email);
    else
        issueSslCertbot(domain, email);
    try {
        execSync("nginx -t && systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
    }
    catch {
        console.warn("nginx reload skipped (run manually)");
    }
}
function cmdDoctor() {
    const checks = {};
    checks.node = process.version;
    checks.port = String(env.port);
    checks.master_secret = Boolean(env.masterSecret);
    checks.tenants_dir = existsSync(env.tenantsDir);
    migrateLegacyConfigIfNeeded();
    checks.tenant_count = String(listTenants().length);
    checks.domains = collectAllDomains().join(",") || "(none)";
    try {
        spawnSync("nginx", ["-t"], { stdio: "pipe" });
        checks.nginx = true;
    }
    catch {
        checks.nginx = false;
    }
    console.log(JSON.stringify(checks, null, 2));
}
function help() {
    console.log(`svp-relay — SimpleVPBot Telegram relay CLI

Usage:
  svp-relay install [install.sh flags]
  svp-relay status
  svp-relay tenants list
  svp-relay tenant show <id>
  svp-relay domains list
  svp-relay domain add <domain> [--tenant id]
  svp-relay domain remove <domain> [--tenant id]
  svp-relay nginx render [--out path]
  svp-relay ssl issue <domain> [--method certbot|acme] [--email addr]
  svp-relay ssl renew [--method certbot|acme]
  svp-relay config migrate
  svp-relay doctor
`);
}
switch (cmd) {
    case "install":
        runInstall();
        break;
    case "status":
        cmdStatus();
        break;
    case "tenants":
        if (sub === "list")
            cmdTenantsList();
        else
            help();
        break;
    case "tenant":
        if (sub === "show" && argv[2])
            cmdTenantShow(argv[2]);
        else
            help();
        break;
    case "domains":
        if (sub === "list")
            cmdDomainsList();
        else
            help();
        break;
    case "domain":
        if (sub === "add" && argv[2])
            cmdDomainAdd(argv[2]);
        else if (sub === "remove" && argv[2])
            cmdDomainRemove(argv[2]);
        else
            help();
        break;
    case "nginx":
        if (sub === "render")
            cmdNginxRender();
        else
            help();
        break;
    case "ssl":
        if (sub === "issue" && argv[2])
            cmdSslIssue(argv[2]);
        else if (sub === "renew")
            renewSsl((flag("--method") || "certbot"));
        else
            help();
        break;
    case "config":
        if (sub === "migrate") {
            console.log(JSON.stringify({ migrated: migrateLegacyConfigIfNeeded() }));
        }
        else
            help();
        break;
    case "doctor":
        cmdDoctor();
        break;
    default:
        help();
}

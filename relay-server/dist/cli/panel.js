import { execSync } from "node:child_process";
import { existsSync } from "node:fs";
import { select, input, confirm } from "@inquirer/prompts";
import { env } from "../env.js";
import { renderNginxConfig, defaultSslPaths } from "./nginx.js";
import { issueSslAcme, issueSslCertbot, renewSsl } from "./ssl.js";
import { pkgRoot } from "./paths.js";
import { formatDoctor, formatStatusSnapshot, getStatusSnapshot, runDoctor, } from "./commands/status.js";
import { domainAdd, domainRemove, domainsListText, } from "./commands/domains.js";
import { followLogs, isRoot, serviceRestart, serviceStart, serviceStatus, serviceStop, tailLogs, } from "./commands/service.js";
import { tenantChoices, tenantShowText, tenantsListText } from "./commands/tenants.js";
import { wpSetupGuide } from "./commands/wp-guide.js";
function pause() {
    return input({ message: "Press Enter to continue" }).then(() => { });
}
async function menuDashboard() {
    const snap = await getStatusSnapshot();
    console.log("\n" + formatStatusSnapshot(snap) + "\n");
    await pause();
}
async function menuService() {
    const action = await select({
        message: "Service (svp-relay)",
        choices: [
            { name: "Status", value: "status" },
            { name: "Start", value: "start" },
            { name: "Stop", value: "stop" },
            { name: "Restart", value: "restart" },
            { name: "Back", value: "back" },
        ],
    });
    if (action === "back")
        return;
    if (!isRoot() && action !== "status") {
        const ok = await confirm({ message: "Continue without root? (may fail)", default: false });
        if (!ok)
            return;
    }
    try {
        if (action === "status")
            serviceStatus();
        else if (action === "start")
            serviceStart();
        else if (action === "stop")
            serviceStop();
        else if (action === "restart")
            serviceRestart();
    }
    catch (e) {
        console.error(e instanceof Error ? e.message : e);
    }
    await pause();
}
async function menuTenants() {
    const action = await select({
        message: "Tenants",
        choices: [
            { name: "List", value: "list" },
            { name: "Show details", value: "show" },
            { name: "Back", value: "back" },
        ],
    });
    if (action === "back")
        return;
    if (action === "list") {
        console.log("\n" + tenantsListText() + "\n");
    }
    else {
        const choices = tenantChoices();
        if (!choices.length) {
            console.log("\nNo tenants yet. Sync config from WordPress first.\n");
        }
        else {
            const id = await select({ message: "Tenant", choices });
            const text = tenantShowText(id);
            console.log("\n" + (text || "not found") + "\n");
        }
    }
    await pause();
}
async function menuDomains() {
    const action = await select({
        message: "Domains",
        choices: [
            { name: "List", value: "list" },
            { name: "Add domain", value: "add" },
            { name: "Remove domain", value: "remove" },
            { name: "Back", value: "back" },
        ],
    });
    if (action === "back")
        return;
    if (action === "list") {
        console.log("\n" + domainsListText() + "\n");
    }
    else if (action === "add") {
        const choices = tenantChoices();
        const tenantId = choices.length
            ? await select({ message: "Tenant", choices })
            : undefined;
        const domain = await input({ message: "Domain (hostname only)" });
        const result = domainAdd(domain, tenantId);
        console.log(result.ok ? `\nAdded: ${JSON.stringify(result)}\n` : `\nError: ${result.error}\n`);
    }
    else {
        const domain = await input({ message: "Domain to remove" });
        const ok = await confirm({ message: `Remove ${domain}?`, default: false });
        if (ok) {
            const result = domainRemove(domain);
            console.log(result.ok ? `\nRemoved: ${JSON.stringify(result)}\n` : `\nError: ${result.error}\n`);
        }
    }
    await pause();
}
async function menuSsl() {
    const action = await select({
        message: "SSL",
        choices: [
            { name: "Issue certificate (wizard)", value: "issue" },
            { name: "Renew certificates", value: "renew" },
            { name: "Show expected cert paths", value: "paths" },
            { name: "Back", value: "back" },
        ],
    });
    if (action === "back")
        return;
    if (action === "renew") {
        const method = await select({
            message: "Renew method",
            choices: [
                { name: "certbot", value: "certbot" },
                { name: "acme.sh", value: "acme" },
            ],
        });
        try {
            renewSsl(method);
        }
        catch (e) {
            console.error(e instanceof Error ? e.message : e);
        }
    }
    else if (action === "paths") {
        const domain = await input({ message: "Domain" });
        const p = defaultSslPaths(domain);
        console.log(`\nCert: ${p.cert}\nKey:  ${p.key}\nExists: cert=${existsSync(p.cert)} key=${existsSync(p.key)}\n`);
    }
    else {
        const domain = await input({ message: "Domain" });
        const method = await select({
            message: "Method",
            choices: [
                { name: "certbot", value: "certbot" },
                { name: "acme.sh", value: "acme" },
            ],
        });
        const email = await input({ message: "Email (optional for certbot)", default: "" });
        if (!isRoot()) {
            const ok = await confirm({ message: "SSL issue usually requires root. Continue?", default: false });
            if (!ok)
                return;
        }
        try {
            if (method === "acme")
                issueSslAcme(domain, email);
            else
                issueSslCertbot(domain, email);
            try {
                execSync("nginx -t && systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
            }
            catch {
                console.warn("nginx reload skipped — run Nginx menu manually");
            }
        }
        catch (e) {
            console.error(e instanceof Error ? e.message : e);
        }
    }
    await pause();
}
async function menuNginx() {
    const action = await select({
        message: "Nginx",
        choices: [
            { name: "Render config", value: "render" },
            { name: "Test config (nginx -t)", value: "test" },
            { name: "Reload nginx", value: "reload" },
            { name: "Show config path", value: "path" },
            { name: "Back", value: "back" },
        ],
    });
    if (action === "back")
        return;
    if (action === "path") {
        console.log(`\n${env.nginxConfigPath}\n`);
    }
    else if (action === "render") {
        const out = renderNginxConfig();
        console.log(`\nWrote ${out}\n`);
        const enable = await confirm({
            message: "Enable site symlink in sites-enabled?",
            default: true,
        });
        if (enable) {
            try {
                execSync(`ln -sf ${env.nginxConfigPath} /etc/nginx/sites-enabled/svp-relay.conf`, { stdio: "inherit", shell: "/bin/bash" });
            }
            catch (e) {
                console.error(e instanceof Error ? e.message : e);
            }
        }
    }
    else if (action === "test") {
        try {
            execSync("nginx -t", { stdio: "inherit", shell: "/bin/bash" });
        }
        catch (e) {
            console.error(e instanceof Error ? e.message : e);
        }
    }
    else if (action === "reload") {
        try {
            execSync("nginx -t && systemctl reload nginx", { stdio: "inherit", shell: "/bin/bash" });
        }
        catch (e) {
            console.error(e instanceof Error ? e.message : e);
        }
    }
    await pause();
}
async function menuWpSetup() {
    const show = await confirm({
        message: "Reveal RELAY_MASTER_SECRET in output?",
        default: false,
    });
    console.log("\n" + wpSetupGuide(show) + "\n");
    await pause();
}
async function menuLogs() {
    const mode = await select({
        message: "Logs",
        choices: [
            { name: "Tail last 100 lines", value: "tail" },
            { name: "Follow (live)", value: "follow" },
            { name: "Back", value: "back" },
        ],
    });
    if (mode === "back")
        return;
    if (mode === "tail")
        tailLogs(100);
    else
        followLogs();
    await pause();
}
async function menuDoctor() {
    console.log("\n" + formatDoctor(runDoctor()) + "\n");
    await pause();
}
async function menuInstall(runInstall) {
    const ok = await confirm({
        message: "Run install.sh (rebuild, systemd, optional nginx)? Requires root.",
        default: false,
    });
    if (!ok)
        return;
    runInstall([]);
}
export async function runPanel(runInstall) {
    console.log("\nSimpleVPBot Relay — Control Panel");
    console.log(`Install: ${pkgRoot()}  Port: ${env.port}\n`);
    for (;;) {
        const choice = await select({
            message: "Main menu",
            choices: [
                { name: "1. Dashboard", value: "dashboard" },
                { name: "2. Service", value: "service" },
                { name: "3. Tenants", value: "tenants" },
                { name: "4. Domains", value: "domains" },
                { name: "5. SSL", value: "ssl" },
                { name: "6. Nginx", value: "nginx" },
                { name: "7. WordPress setup", value: "wp" },
                { name: "8. Logs", value: "logs" },
                { name: "9. Doctor", value: "doctor" },
                { name: "10. Install / update", value: "install" },
                { name: "0. Exit", value: "exit" },
            ],
        });
        if (choice === "exit") {
            console.log("Bye.");
            return;
        }
        if (choice === "dashboard")
            await menuDashboard();
        else if (choice === "service")
            await menuService();
        else if (choice === "tenants")
            await menuTenants();
        else if (choice === "domains")
            await menuDomains();
        else if (choice === "ssl")
            await menuSsl();
        else if (choice === "nginx")
            await menuNginx();
        else if (choice === "wp")
            await menuWpSetup();
        else if (choice === "logs")
            await menuLogs();
        else if (choice === "doctor")
            await menuDoctor();
        else if (choice === "install")
            await menuInstall(runInstall);
    }
}

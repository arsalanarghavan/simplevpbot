import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { collectAllDomains } from "../store.js";
import { env } from "../env.js";
const templatePath = resolve(dirname(fileURLToPath(import.meta.url)), "../../templates/nginx-site.conf");
export function renderNginxConfig(opts) {
    const domains = (opts?.domains?.length ? opts.domains : collectAllDomains()).join(" ");
    const port = opts?.port ?? env.port;
    const sslCert = opts?.sslCert || "/etc/letsencrypt/live/DOMAIN/fullchain.pem";
    const sslKey = opts?.sslKey || "/etc/letsencrypt/live/DOMAIN/privkey.pem";
    let tpl = readFileSync(templatePath, "utf8");
    tpl = tpl.replace(/\{\{DOMAINS\}\}/g, domains || "_");
    tpl = tpl.replace(/\{\{PORT\}\}/g, String(port));
    tpl = tpl.replace(/\{\{SSL_CERT\}\}/g, sslCert);
    tpl = tpl.replace(/\{\{SSL_KEY\}\}/g, sslKey);
    const out = opts?.outPath || env.nginxConfigPath;
    mkdirSync(dirname(out), { recursive: true });
    writeFileSync(out, tpl, { mode: 0o644 });
    return out;
}
export function defaultSslPaths(domain) {
    const d = domain.replace(/^https?:\/\//, "").split("/")[0];
    return {
        cert: `/etc/letsencrypt/live/${d}/fullchain.pem`,
        key: `/etc/letsencrypt/live/${d}/privkey.pem`,
    };
}
export function acmeSslPaths(domain) {
    const d = domain.replace(/^https?:\/\//, "").split("/")[0];
    const home = process.env.HOME || "/root";
    return {
        cert: `${home}/.acme.sh/${d}_ecc/fullchain.cer`,
        key: `${home}/.acme.sh/${d}_ecc/${d}.key`,
    };
}

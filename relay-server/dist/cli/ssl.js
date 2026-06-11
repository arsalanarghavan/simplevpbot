import { execSync } from "node:child_process";
import { existsSync, mkdirSync } from "node:fs";
import { renderAllNginx, defaultSslPaths, acmeSslPaths } from "./nginx.js";
export function issueSslCertbot(domain, email) {
    const d = domain.replace(/^https?:\/\//, "").split("/")[0];
    const emailFlag = email ? `-m ${email}` : "--register-unsafely-without-email";
    execSync(`certbot certonly --nginx -d ${d} ${emailFlag} --agree-tos --non-interactive`, {
        stdio: "inherit",
    });
    const paths = defaultSslPaths(d);
    renderAllNginx({ domains: [d], sslCert: paths.cert, sslKey: paths.key });
}
export function issueSslAcme(domain, email) {
    const d = domain.replace(/^https?:\/\//, "").split("/")[0];
    const home = process.env.HOME || "/root";
    const acme = `${home}/.acme.sh/acme.sh`;
    if (!existsSync(acme)) {
        execSync(`curl -fsSL https://get.acme.sh | sh -s email=${email || "admin@localhost"}`, { stdio: "inherit", shell: "/bin/bash" });
    }
    mkdirSync("/var/www/certbot", { recursive: true });
    execSync(`${acme} --issue -d ${d} --nginx`, { stdio: "inherit" });
    const paths = acmeSslPaths(d);
    renderAllNginx({ domains: [d], sslCert: paths.cert, sslKey: paths.key });
}
export function renewSsl(method) {
    if (method === "certbot") {
        execSync("certbot renew --quiet", { stdio: "inherit" });
    }
    else {
        const home = process.env.HOME || "/root";
        execSync(`${home}/.acme.sh/acme.sh --renew-all`, { stdio: "inherit" });
    }
}

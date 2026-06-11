#!/usr/bin/env bash
set -euo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/svp-relay}"
SERVICE_USER="${SERVICE_USER:-svp-relay}"
DOMAIN=""
EMAIL=""
SSL_METHOD=""
NO_NGINX=0
NO_SYSTEMD=0
WP_URL=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2 ;;
    --email) EMAIL="$2"; shift 2 ;;
    --ssl) SSL_METHOD="$2"; shift 2 ;;
    --wp-url) WP_URL="$2"; shift 2 ;;
    --no-nginx) NO_NGINX=1; shift ;;
    --no-systemd) NO_SYSTEMD=1; shift ;;
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root (sudo)."
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "[1/7] Installing Node.js 20 if needed..."
if ! command -v node >/dev/null 2>&1 || [[ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

echo "[2/7] Creating service user and directory..."
id -u "$SERVICE_USER" &>/dev/null || useradd --system --home "$INSTALL_DIR" --shell /usr/sbin/nologin "$SERVICE_USER"
mkdir -p "$INSTALL_DIR"
rsync -a --delete --exclude node_modules "$SRC_DIR/" "$INSTALL_DIR/"
chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR"

echo "[3/7] Building relay..."
cd "$INSTALL_DIR"
sudo -u "$SERVICE_USER" npm ci
sudo -u "$SERVICE_USER" npm run build

echo "[4/7] Writing .env..."
MASTER_SECRET="$(openssl rand -hex 32)"
ENV_FILE="$INSTALL_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  cat >"$ENV_FILE" <<EOF
PORT=8787
RELAY_MASTER_SECRET=$MASTER_SECRET
RELAY_SHARED_SECRET=$MASTER_SECRET
DATA_DIR=$INSTALL_DIR/data
TENANTS_DIR=$INSTALL_DIR/data/tenants
NGINX_CONFIG_PATH=/etc/nginx/sites-available/svp-relay.conf
EOF
  chmod 600 "$ENV_FILE"
  chown "$SERVICE_USER:$SERVICE_USER" "$ENV_FILE"
  echo "RELAY_MASTER_SECRET (copy to WordPress relay shared secret):"
  echo "$MASTER_SECRET"
fi

echo "[5/7] systemd unit..."
if [[ "$NO_SYSTEMD" -eq 0 ]]; then
  cat >/etc/systemd/system/svp-relay.service <<EOF
[Unit]
Description=SimpleVPBot Telegram Relay
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
WorkingDirectory=$INSTALL_DIR
EnvironmentFile=$INSTALL_DIR/.env
ExecStart=/usr/bin/node dist/index.js
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable svp-relay
  systemctl restart svp-relay
fi

echo "[6/7] nginx..."
if [[ "$NO_NGINX" -eq 0 ]] && command -v nginx >/dev/null 2>&1; then
  apt-get install -y nginx || true
  mkdir -p /var/www/certbot
  if [[ -n "$DOMAIN" ]]; then
    sudo -u "$SERVICE_USER" node dist/cli/svp-relay.js domain add "$DOMAIN" || true
    sudo -u "$SERVICE_USER" node dist/cli/svp-relay.js nginx render
    ln -sf /etc/nginx/sites-available/svp-relay.conf /etc/nginx/sites-enabled/svp-relay.conf 2>/dev/null || true
    if [[ -n "$SSL_METHOD" ]]; then
      node dist/cli/svp-relay.js ssl issue "$DOMAIN" --method "$SSL_METHOD" ${EMAIL:+--email "$EMAIL"}
    fi
    nginx -t && systemctl reload nginx
  fi
fi

echo "[7/7] CLI on PATH..."
ln -sf "$INSTALL_DIR/dist/cli/svp-relay.js" /usr/local/bin/svp-relay
chmod +x /usr/local/bin/svp-relay 2>/dev/null || true

echo "Done."
echo "Install dir: $INSTALL_DIR"
echo "Control panel: svp-relay"
echo "Health: curl -s http://127.0.0.1:8787/health"
[[ -n "$WP_URL" ]] && echo "Configure WordPress at: $WP_URL → Site settings → Telegram relay"

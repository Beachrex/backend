#!/bin/bash

################################################################################
# Meilisearch Hetzner Setup Script
# 
# This script automates the installation and configuration of Meilisearch
# on a fresh Hetzner Ubuntu 24.04 server.
#
# Usage:
#   1. SSH into your Hetzner server
#   2. Download this script: wget https://raw.githubusercontent.com/...
#   3. Make executable: chmod +x setup-meilisearch-hetzner.sh
#   4. Run: sudo ./setup-meilisearch-hetzner.sh
#
# Author: BeachRex Team
# Date: 2025-12-01
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

print_header() {
    echo ""
    echo "=================================="
    echo "$1"
    echo "=================================="
    echo ""
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_header "Meilisearch Hetzner Setup"

# Prompt for configuration
print_info "Please provide the following information:"
echo ""

read -p "Domain for Meilisearch (e.g., search.beachrex.com): " DOMAIN
read -p "Email for Let's Encrypt SSL: " EMAIL

# Generate master key
print_info "Generating secure master key..."
MASTER_KEY=$(openssl rand -base64 32)

echo ""
print_success "Configuration:"
echo "  Domain: $DOMAIN"
echo "  Email: $EMAIL"
echo "  Master Key: $MASTER_KEY"
echo ""
print_info "⚠️  SAVE THE MASTER KEY ABOVE! You'll need it for Laravel configuration."
echo ""
read -p "Press Enter to continue or Ctrl+C to cancel..."

# Step 1: Update system
print_header "Step 1: Updating System"
apt update
apt upgrade -y
print_success "System updated"

# Step 2: Install dependencies
print_header "Step 2: Installing Dependencies"
apt install -y curl wget ufw nginx certbot python3-certbot-nginx
print_success "Dependencies installed"

# Step 3: Install Meilisearch
print_header "Step 3: Installing Meilisearch"
curl -L https://install.meilisearch.com | sh
mv ./meilisearch /usr/local/bin/
chmod +x /usr/local/bin/meilisearch
print_success "Meilisearch installed"

# Verify installation
MEILI_VERSION=$(meilisearch --version)
print_info "Installed: $MEILI_VERSION"

# Step 4: Create user and directories
print_header "Step 4: Creating User and Directories"
useradd -r -s /bin/false -d /var/lib/meilisearch meilisearch || true
mkdir -p /var/lib/meilisearch/data
mkdir -p /var/lib/meilisearch/dumps
chown -R meilisearch:meilisearch /var/lib/meilisearch
print_success "User and directories created"

# Step 5: Create systemd service
print_header "Step 5: Creating Systemd Service"
cat > /etc/systemd/system/meilisearch.service <<EOF
[Unit]
Description=Meilisearch
After=network.target

[Service]
Type=simple
User=meilisearch
Group=meilisearch
WorkingDirectory=/var/lib/meilisearch

# Environment variables
Environment="MEILI_HTTP_ADDR=127.0.0.1:7700"
Environment="MEILI_MASTER_KEY=$MASTER_KEY"
Environment="MEILI_ENV=production"
Environment="MEILI_DB_PATH=/var/lib/meilisearch/data"
Environment="MEILI_DUMP_DIR=/var/lib/meilisearch/dumps"
Environment="MEILI_NO_ANALYTICS=true"
Environment="MEILI_LOG_LEVEL=INFO"
Environment="MEILI_MAX_INDEXING_MEMORY=2GB"
Environment="MEILI_MAX_INDEXING_THREADS=2"

ExecStart=/usr/local/bin/meilisearch

# Restart policy
Restart=on-failure
RestartSec=5s

# Security hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/meilisearch

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable meilisearch
systemctl start meilisearch
sleep 3
print_success "Meilisearch service created and started"

# Check service status
if systemctl is-active --quiet meilisearch; then
    print_success "Meilisearch is running"
else
    print_error "Meilisearch failed to start. Check logs: journalctl -u meilisearch -n 50"
    exit 1
fi

# Step 6: Configure firewall
print_header "Step 6: Configuring Firewall"
ufw --force enable
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
print_success "Firewall configured"

# Step 7: Configure Nginx
print_header "Step 7: Configuring Nginx"
cat > /etc/nginx/sites-available/meilisearch <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    # Redirect to HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN;

    # SSL certificates (will be added by Certbot)
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;

    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logging
    access_log /var/log/nginx/meilisearch-access.log;
    error_log /var/log/nginx/meilisearch-error.log;

    # Proxy to Meilisearch
    location / {
        proxy_pass http://127.0.0.1:7700;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;

        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;

        # Buffer settings
        proxy_buffering off;
        proxy_request_buffering off;
    }
}
EOF

ln -sf /etc/nginx/sites-available/meilisearch /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx
print_success "Nginx configured"

# Step 8: Get SSL certificate
print_header "Step 8: Getting SSL Certificate"
print_info "Make sure DNS is configured: $DOMAIN -> $(curl -s ifconfig.me)"
echo ""
read -p "Press Enter when DNS is ready or Ctrl+C to cancel..."

certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $EMAIL --redirect

if [ $? -eq 0 ]; then
    print_success "SSL certificate obtained"
else
    print_error "Failed to get SSL certificate. Check DNS configuration."
    exit 1
fi

# Step 9: Test installation
print_header "Step 9: Testing Installation"

# Test local health
LOCAL_HEALTH=$(curl -s http://127.0.0.1:7700/health)
if [[ $LOCAL_HEALTH == *"available"* ]]; then
    print_success "Local health check passed"
else
    print_error "Local health check failed"
fi

# Test HTTPS health
HTTPS_HEALTH=$(curl -s https://$DOMAIN/health)
if [[ $HTTPS_HEALTH == *"available"* ]]; then
    print_success "HTTPS health check passed"
else
    print_error "HTTPS health check failed"
fi

# Step 10: Create backup script
print_header "Step 10: Creating Backup Script"
cat > /usr/local/bin/meilisearch-backup.sh <<EOF
#!/bin/bash
# Meilisearch Backup Script

MASTER_KEY="$MASTER_KEY"
BACKUP_DIR="/var/backups/meilisearch"
DATE=\$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p \$BACKUP_DIR

# Create dump
curl -X POST "http://127.0.0.1:7700/dumps" \\
  -H "Authorization: Bearer \$MASTER_KEY"

# Wait for dump to complete
sleep 30

# Copy dump to backup directory
cp /var/lib/meilisearch/dumps/*.dump \$BACKUP_DIR/meilisearch_\$DATE.dump 2>/dev/null || true

# Keep only last 7 days of backups
find \$BACKUP_DIR -name "*.dump" -mtime +7 -delete

echo "Backup completed: meilisearch_\$DATE.dump"
EOF

chmod +x /usr/local/bin/meilisearch-backup.sh
print_success "Backup script created"

# Add to crontab
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/meilisearch-backup.sh >> /var/log/meilisearch-backup.log 2>&1") | crontab -
print_success "Backup cron job added (daily at 2 AM)"

# Final summary
print_header "Installation Complete!"

echo ""
print_success "Meilisearch is now running on: https://$DOMAIN"
echo ""
echo "Configuration Summary:"
echo "  Domain: $DOMAIN"
echo "  Master Key: $MASTER_KEY"
echo "  Data Directory: /var/lib/meilisearch/data"
echo "  Dumps Directory: /var/lib/meilisearch/dumps"
echo ""
echo "Next Steps:"
echo "  1. Save the master key above in a secure location"
echo "  2. Update Laravel .env with:"
echo "     SCOUT_DRIVER=meilisearch"
echo "     SCOUT_QUEUE=true"
echo "     MEILISEARCH_HOST=https://$DOMAIN"
echo "     MEILISEARCH_KEY=$MASTER_KEY"
echo "  3. Index data: php artisan search:index"
echo "  4. Test search: curl https://$DOMAIN/health"
echo ""
echo "Useful Commands:"
echo "  Service status: systemctl status meilisearch"
echo "  View logs: journalctl -u meilisearch -f"
echo "  Restart: systemctl restart meilisearch"
echo "  Manual backup: /usr/local/bin/meilisearch-backup.sh"
echo ""
print_success "Setup completed successfully!"


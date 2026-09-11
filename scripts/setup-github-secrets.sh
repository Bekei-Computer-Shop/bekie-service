#!/bin/bash
set -euo pipefail

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_step() {
    echo -e "${GREEN}➜${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Check if gh CLI is installed
if ! command -v gh &> /dev/null; then
    echo -e "${YELLOW}GitHub CLI (gh) not found. Install from: https://cli.github.com${NC}"
    exit 1
fi

# Verify authenticated
if ! gh auth status &> /dev/null; then
    echo "Not authenticated with GitHub. Run: gh auth login"
    exit 1
fi

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}Setting up GitHub Secrets and Variables${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"

# Your values
GCP_PROJECT_ID="114227311375"
WIF_PROVIDER="projects/114227311375/locations/global/workloadIdentityPools/github-pool/providers/github-provider"
WIF_SERVICE_ACCOUNT="github-actions-ci@project-87b51d11-3d16-43db-9d0.iam.gserviceaccount.com"

# ========================
# GitHub Secrets (2)
# ========================
echo ""
echo -e "${BLUE}Setting GitHub Secrets...${NC}"

print_step "Adding WIF_PROVIDER secret"
echo "$WIF_PROVIDER" | gh secret set WIF_PROVIDER
print_success "WIF_PROVIDER set"

print_step "Adding WIF_SERVICE_ACCOUNT secret"
echo "$WIF_SERVICE_ACCOUNT" | gh secret set WIF_SERVICE_ACCOUNT
print_success "WIF_SERVICE_ACCOUNT set"

# ========================
# GitHub Variables (25)
# ========================
echo ""
echo -e "${BLUE}Setting GitHub Variables...${NC}"

# Deployment Config
gh variable set GCP_PROJECT_ID --body "$GCP_PROJECT_ID"
print_success "GCP_PROJECT_ID"

gh variable set GCP_REGION --body "us-central1"
print_success "GCP_REGION"

gh variable set CLOUD_RUN_SERVICE_NAME --body "bekie-service"
print_success "CLOUD_RUN_SERVICE_NAME"

gh variable set APP_URL --body "https://api.yourdomain.com"
print_success "APP_URL (⚠ Update this with your actual domain)"

# Logging
gh variable set LOG_CHANNEL --body "stderr"
print_success "LOG_CHANNEL"

gh variable set LOG_LEVEL --body "info"
print_success "LOG_LEVEL"

# Cache & Session
gh variable set CACHE_STORE --body "redis"
print_success "CACHE_STORE"

gh variable set SESSION_DRIVER --body "redis"
print_success "SESSION_DRIVER"

gh variable set QUEUE_CONNECTION --body "redis"
print_success "QUEUE_CONNECTION"

# Database
gh variable set RUN_MIGRATIONS --body "false"
print_success "RUN_MIGRATIONS"

# Cloud Run Sizing
gh variable set CLOUD_RUN_MEMORY --body "512Mi"
print_success "CLOUD_RUN_MEMORY"

gh variable set CLOUD_RUN_CPU --body "1"
print_success "CLOUD_RUN_CPU"

gh variable set CLOUD_RUN_TIMEOUT --body "300"
print_success "CLOUD_RUN_TIMEOUT"

gh variable set CLOUD_RUN_MAX_INSTANCES --body "10"
print_success "CLOUD_RUN_MAX_INSTANCES"

gh variable set CLOUD_RUN_MIN_INSTANCES --body "1"
print_success "CLOUD_RUN_MIN_INSTANCES"

# Secret Manager References (13)
echo ""
echo -e "${BLUE}Setting Secret Manager references...${NC}"

gh variable set GCP_SECRET_APP_KEY --body "app-key:latest"
print_success "GCP_SECRET_APP_KEY"

gh variable set GCP_SECRET_DB_HOST --body "db-host:latest"
print_success "GCP_SECRET_DB_HOST"

gh variable set GCP_SECRET_DB_PORT --body "db-port:latest"
print_success "GCP_SECRET_DB_PORT"

gh variable set GCP_SECRET_DB_DATABASE --body "db-database:latest"
print_success "GCP_SECRET_DB_DATABASE"

gh variable set GCP_SECRET_DB_USERNAME --body "db-username:latest"
print_success "GCP_SECRET_DB_USERNAME"

gh variable set GCP_SECRET_DB_PASSWORD --body "db-password:latest"
print_success "GCP_SECRET_DB_PASSWORD"

gh variable set GCP_SECRET_DB_SSLMODE --body "db-sslmode:latest"
print_success "GCP_SECRET_DB_SSLMODE"

gh variable set GCP_SECRET_REDIS_HOST --body "redis-host:latest"
print_success "GCP_SECRET_REDIS_HOST"

gh variable set GCP_SECRET_REDIS_PORT --body "redis-port:latest"
print_success "GCP_SECRET_REDIS_PORT"

gh variable set GCP_SECRET_REDIS_PASSWORD --body "redis-password:latest"
print_success "GCP_SECRET_REDIS_PASSWORD"

gh variable set GCP_SECRET_JWT_SECRET --body "jwt-secret:latest"
print_success "GCP_SECRET_JWT_SECRET"

gh variable set GCP_SECRET_CLOUDINARY_CLOUD_NAME --body "cloudinary-cloud-name:latest"
print_success "GCP_SECRET_CLOUDINARY_CLOUD_NAME"

gh variable set GCP_SECRET_CLOUDINARY_API_KEY --body "cloudinary-api-key:latest"
print_success "GCP_SECRET_CLOUDINARY_API_KEY"

gh variable set GCP_SECRET_CLOUDINARY_API_SECRET --body "cloudinary-api-secret:latest"
print_success "GCP_SECRET_CLOUDINARY_API_SECRET"

# Summary
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ All GitHub Secrets and Variables configured!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "1. ⚠️  Update APP_URL variable with your actual domain"
echo "2. ⚠️  Create 13 Google Secret Manager secrets (see guide)"
echo "3. Push to main branch to trigger deployment"
echo ""
echo "View configured items:"
echo "  gh secret list"
echo "  gh variable list"

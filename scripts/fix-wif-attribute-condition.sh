#!/bin/bash
set -euo pipefail

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_header() {
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
}

print_step() {
    echo -e "${GREEN}➜${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Configuration
PROJECT_ID="project-87b51d11-3d16-43db-9d0"
GITHUB_OWNER="Bekei-Computer-Shop"
GITHUB_REPO="bekie-service"
POOL_ID="github-pool"
PROVIDER_ID="github-provider"

print_header "Fix WIF Attribute Condition"

# Set project
gcloud config set project $PROJECT_ID --quiet

# Current configuration
print_step "Current Workload Identity Provider configuration:"
gcloud iam workload-identity-providers describe $PROVIDER_ID \
    --location=global \
    --workload-identity-pool=$POOL_ID \
    --project=$PROJECT_ID \
    --format="table(name,displayName,attributeMapping,attributeCondition)"

echo ""
print_step "Updating attribute condition to accept GitHub tokens..."

# Update with proper attribute condition that checks GitHub repository claims
# This allows any GitHub token where:
# - The issuer is GitHub
# - The repository owner matches
# - The repository name matches
gcloud iam workload-identity-providers update $PROVIDER_ID \
    --location=global \
    --workload-identity-pool=$POOL_ID \
    --project=$PROJECT_ID \
    --attribute-condition="assertion.repository_owner == '${GITHUB_OWNER}' && assertion.repository == '${GITHUB_REPO}'" \
    --quiet

print_success "Attribute condition updated"

echo ""
print_step "Updated configuration:"
gcloud iam workload-identity-providers describe $PROVIDER_ID \
    --location=global \
    --workload-identity-pool=$POOL_ID \
    --project=$PROJECT_ID \
    --format="table(name,displayName,attributeMapping,attributeCondition)"

echo ""
print_header "Attribute Condition Fixed"

echo ""
echo -e "${GREEN}The attribute condition has been updated to:${NC}"
echo "  assertion.repository_owner == '${GITHUB_OWNER}'"
echo "  && assertion.repository == '${GITHUB_REPO}'"
echo ""
echo "This accepts GitHub OIDC tokens from:"
echo "  Organization/Owner: ${GITHUB_OWNER}"
echo "  Repository: ${GITHUB_REPO}"
echo "  Any branch"
echo ""

print_step "Next: Push to main branch to trigger GitHub Actions"
echo ""
echo "Commands:"
echo "  git checkout main"
echo "  git merge cicd-pibline-setup"
echo "  git push origin main"
echo ""

print_success "Ready to deploy! ✓"

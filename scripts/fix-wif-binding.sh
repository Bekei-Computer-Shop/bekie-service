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

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Configuration
PROJECT_ID="project-87b51d11-3d16-43db-9d0"
GITHUB_OWNER="Bekei-Computer-Shop"
GITHUB_REPO="bekie-service"
SERVICE_ACCOUNT_NAME="github-actions-ci"

print_header "Fix Workload Identity Federation (WIF) Binding"

# Verify authentication
echo ""
print_step "Verifying Google Cloud authentication..."
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" &> /dev/null; then
    print_error "Not authenticated with gcloud. Run: gcloud auth login"
    exit 1
fi

ACTIVE_ACCOUNT=$(gcloud auth list --filter=status:ACTIVE --format="value(account)")
print_success "Authenticated as: $ACTIVE_ACCOUNT"

# Set project
print_step "Setting Google Cloud project..."
gcloud config set project $PROJECT_ID --quiet
print_success "Project set to: $PROJECT_ID"

# Get project number
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")
print_success "Project number: $PROJECT_NUMBER"

# Verify service account exists
print_step "Verifying service account exists..."
SERVICE_ACCOUNT_EMAIL="${SERVICE_ACCOUNT_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

if ! gcloud iam service-accounts describe $SERVICE_ACCOUNT_EMAIL --project=$PROJECT_ID &> /dev/null; then
    print_error "Service account not found: $SERVICE_ACCOUNT_EMAIL"
    print_warning "Creating service account..."
    gcloud iam service-accounts create $SERVICE_ACCOUNT_NAME \
        --display-name="GitHub Actions CI/CD" \
        --project=$PROJECT_ID
    print_success "Service account created"
else
    print_success "Service account exists: $SERVICE_ACCOUNT_EMAIL"
fi

# Verify WIF pool exists
print_step "Verifying Workload Identity Federation pool..."
POOL_ID="github-pool"
PROVIDER_ID="github-provider"

if ! gcloud iam workload-identity-pools describe $POOL_ID \
    --location=global \
    --project=$PROJECT_ID &> /dev/null; then
    print_error "Workload Identity Pool not found: $POOL_ID"
    print_step "Creating pool..."
    gcloud iam workload-identity-pools create $POOL_ID \
        --project=$PROJECT_ID \
        --location=global \
        --display-name="GitHub Actions Pool" \
        --quiet
    print_success "Pool created"
else
    print_success "Workload Identity Pool exists: $POOL_ID"
fi

if ! gcloud iam workload-identity-providers describe $PROVIDER_ID \
    --location=global \
    --workload-identity-pool=$POOL_ID \
    --project=$PROJECT_ID &> /dev/null; then
    print_error "OIDC Provider not found: $PROVIDER_ID"
    print_step "Creating OIDC provider..."
    gcloud iam workload-identity-providers create-oidc $PROVIDER_ID \
        --project=$PROJECT_ID \
        --location=global \
        --workload-identity-pool=$POOL_ID \
        --display-name="GitHub" \
        --attribute-mapping="google.subject=assertion.sub,attribute.actor=assertion.actor,attribute.repository_owner=assertion.repository_owner" \
        --issuer-uri="https://token.actions.githubusercontent.com" \
        --attribute-condition="assertion.aud == 'https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}'" \
        --quiet
    print_success "OIDC Provider created"
else
    print_success "OIDC Provider exists: $PROVIDER_ID"
fi

# Fix the binding
print_step "Fixing Workload Identity Federation binding..."

WIF_PROVIDER="projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/${POOL_ID}/providers/${PROVIDER_ID}"

# Clear old bindings first (if any)
echo ""
print_warning "Removing any existing incorrect bindings..."
EXISTING_BINDINGS=$(gcloud iam service-accounts get-iam-policy $SERVICE_ACCOUNT_EMAIL \
    --project=$PROJECT_ID \
    --flatten="bindings[].members" \
    --filter="bindings.members:principalSet://goog/github.com" \
    --format="value(bindings.members)" 2>/dev/null || echo "")

if [ -n "$EXISTING_BINDINGS" ]; then
    while IFS= read -r binding; do
        if [ -n "$binding" ]; then
            print_step "Removing: $binding"
            gcloud iam service-accounts remove-iam-policy-binding $SERVICE_ACCOUNT_EMAIL \
                --project=$PROJECT_ID \
                --role="roles/iam.workloadIdentityUser" \
                --member="$binding" \
                --quiet 2>/dev/null || true
        fi
    done <<< "$EXISTING_BINDINGS"
fi

# Add the correct binding
echo ""
print_step "Adding correct Workload Identity User binding..."
gcloud iam service-accounts add-iam-policy-binding $SERVICE_ACCOUNT_EMAIL \
    --project=$PROJECT_ID \
    --role="roles/iam.workloadIdentityUser" \
    --member="principalSet://goog/github.com/${GITHUB_OWNER}/${GITHUB_REPO}/ref:refs/heads/main" \
    --quiet

print_success "Binding added for: ${GITHUB_OWNER}/${GITHUB_REPO} (main branch only)"

# Verify the binding
echo ""
print_step "Verifying binding..."
gcloud iam service-accounts get-iam-policy $SERVICE_ACCOUNT_EMAIL \
    --project=$PROJECT_ID \
    --flatten="bindings[].members" \
    --filter="bindings.members:principalSet://goog/github.com" \
    --format="table(bindings.members)" || echo "No GitHub bindings found"

# Display summary
echo ""
print_header "Configuration Summary"

echo ""
echo -e "${BLUE}Workload Identity Federation:${NC}"
echo "  Pool ID:        $POOL_ID"
echo "  Provider ID:    $PROVIDER_ID"
echo "  WIF Provider:   $WIF_PROVIDER"

echo ""
echo -e "${BLUE}GitHub Configuration:${NC}"
echo "  Owner:          $GITHUB_OWNER"
echo "  Repository:     $GITHUB_REPO"
echo "  Branch:         main"

echo ""
echo -e "${BLUE}Service Account:${NC}"
echo "  Email:          $SERVICE_ACCOUNT_EMAIL"
echo "  Project:        $PROJECT_ID"
echo "  Project Number: $PROJECT_NUMBER"

echo ""
echo -e "${BLUE}GitHub Secrets (already configured):${NC}"
echo "  WIF_PROVIDER:"
echo "    $WIF_PROVIDER"
echo ""
echo "  WIF_SERVICE_ACCOUNT:"
echo "    $SERVICE_ACCOUNT_EMAIL"

echo ""
print_header "Next Steps"

echo ""
echo "1. Ensure your GitHub Secrets are set:"
echo "   - WIF_PROVIDER: $WIF_PROVIDER"
echo "   - WIF_SERVICE_ACCOUNT: $SERVICE_ACCOUNT_EMAIL"
echo ""
echo "2. Merge cicd-pibline-setup into main branch:"
echo "   git checkout main"
echo "   git merge cicd-pibline-setup"
echo "   git push origin main"
echo ""
echo "3. GitHub Actions will auto-trigger and should now authenticate successfully!"
echo ""

print_success "WIF binding fixed! ✓"

#!/bin/bash
set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_header() {
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
}

print_step() {
    echo -e "${GREEN}➜${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    print_header "Checking Prerequisites"

    local missing=0

    # Check gcloud
    if ! command -v gcloud &> /dev/null; then
        print_error "gcloud CLI not found. Install from: https://cloud.google.com/sdk/docs/install"
        missing=$((missing + 1))
    else
        print_success "gcloud CLI found"
    fi

    # Check if logged in
    if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" &> /dev/null; then
        print_error "Not authenticated with gcloud. Run: gcloud auth login"
        missing=$((missing + 1))
    else
        local active_account=$(gcloud auth list --filter=status:ACTIVE --format="value(account)")
        print_success "Authenticated as: $active_account"
    fi

    # Check git
    if ! command -v git &> /dev/null; then
        print_error "git not found"
        missing=$((missing + 1))
    else
        print_success "git found"
    fi

    if [ $missing -gt 0 ]; then
        print_error "Please install missing tools and try again"
        exit 1
    fi
}

# Get project ID
get_project_info() {
    print_header "Project Configuration"

    local default_project=$(gcloud config get-value project 2>/dev/null || echo "")

    if [ -n "$default_project" ]; then
        read -p "GCP Project ID [${default_project}]: " PROJECT_ID
        PROJECT_ID="${PROJECT_ID:-$default_project}"
    else
        read -p "GCP Project ID: " PROJECT_ID
    fi

    if [ -z "$PROJECT_ID" ]; then
        print_error "Project ID cannot be empty"
        exit 1
    fi

    # Verify project exists
    if ! gcloud projects describe "$PROJECT_ID" &> /dev/null; then
        print_error "Project '$PROJECT_ID' not found or not accessible"
        exit 1
    fi

    gcloud config set project "$PROJECT_ID"
    PROJECT_NUMBER=$(gcloud projects describe "$PROJECT_ID" --format="value(projectNumber)")

    print_success "Project: $PROJECT_ID"
    print_success "Project Number: $PROJECT_NUMBER"
}

# Get GitHub info
get_github_info() {
    print_header "GitHub Configuration"

    read -p "GitHub Repository Owner (username): " GITHUB_OWNER
    read -p "GitHub Repository Name (default: sana-project): " GITHUB_REPO
    GITHUB_REPO="${GITHUB_REPO:-sana-project}"

    if [ -z "$GITHUB_OWNER" ] || [ -z "$GITHUB_REPO" ]; then
        print_error "GitHub owner and repo are required"
        exit 1
    fi

    print_success "GitHub: ${GITHUB_OWNER}/${GITHUB_REPO}"
}

# Enable APIs
enable_apis() {
    print_header "Enabling Google Cloud APIs"

    local apis=(
        "artifactregistry.googleapis.com"
        "run.googleapis.com"
        "cloudbuild.googleapis.com"
        "sqladmin.googleapis.com"
        "redis.googleapis.com"
        "secretmanager.googleapis.com"
        "iam.googleapis.com"
        "iamcredentials.googleapis.com"
        "sts.googleapis.com"
    )

    for api in "${apis[@]}"; do
        print_step "Enabling $api..."
        gcloud services enable "$api" --quiet
    done

    print_success "All APIs enabled"
}

# Create Artifact Registry
create_artifact_registry() {
    print_header "Creating Artifact Registry Repository"

    local repo_name="bekie-service"
    local region="us-central1"

    # Check if repository already exists
    if gcloud artifacts repositories describe "$repo_name" \
        --location="$region" \
        &> /dev/null; then
        print_warning "Repository '$repo_name' already exists, skipping"
        return
    fi

    print_step "Creating Docker repository: $repo_name"
    gcloud artifacts repositories create "$repo_name" \
        --location="$region" \
        --repository-format=docker \
        --description="Bekie Service API Docker images" \
        --quiet

    print_success "Artifact Registry created: $repo_name"
}

# Create service accounts
create_service_accounts() {
    print_header "Creating Service Accounts"

    # Cloud Run service account
    local run_sa="bekie-run"
    if gcloud iam service-accounts describe "${run_sa}@${PROJECT_ID}.iam.gserviceaccount.com" &> /dev/null; then
        print_warning "Service account '$run_sa' already exists"
    else
        print_step "Creating Cloud Run service account: $run_sa"
        gcloud iam service-accounts create "$run_sa" \
            --display-name="Bekie Service Cloud Run Account" \
            --quiet
        print_success "Service account created: $run_sa"
    fi

    # GitHub Actions CI/CD service account
    local ci_sa="github-actions-ci"
    if gcloud iam service-accounts describe "${ci_sa}@${PROJECT_ID}.iam.gserviceaccount.com" &> /dev/null; then
        print_warning "Service account '$ci_sa' already exists"
    else
        print_step "Creating GitHub Actions service account: $ci_sa"
        gcloud iam service-accounts create "$ci_sa" \
            --display-name="GitHub Actions CI/CD" \
            --quiet
        print_success "Service account created: $ci_sa"
    fi
}

# Grant IAM roles
grant_iam_roles() {
    print_header "Granting IAM Roles"

    local run_sa="bekie-run@${PROJECT_ID}.iam.gserviceaccount.com"
    local ci_sa="github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com"

    print_step "Granting roles to Cloud Run service account..."
    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$run_sa" \
        --role="roles/run.invoker" \
        --quiet

    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$run_sa" \
        --role="roles/artifactregistry.reader" \
        --quiet

    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$run_sa" \
        --role="roles/secretmanager.secretAccessor" \
        --quiet

    print_success "Cloud Run service account roles granted"

    print_step "Granting roles to GitHub Actions service account..."
    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$ci_sa" \
        --role="roles/artifactregistry.writer" \
        --quiet

    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$ci_sa" \
        --role="roles/run.developer" \
        --quiet

    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$ci_sa" \
        --role="roles/run.admin" \
        --quiet

    gcloud projects add-iam-policy-binding "$PROJECT_ID" \
        --member="serviceAccount:$ci_sa" \
        --role="roles/iam.serviceAccountUser" \
        --quiet

    print_success "GitHub Actions service account roles granted"
}

# Setup Workload Identity Federation
setup_wif() {
    print_header "Setting Up Workload Identity Federation (OIDC)"

    local pool_id="github-pool"
    local provider_id="github-provider"
    local region="global"
    local ci_sa="github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com"

    # Create pool
    if gcloud iam workload-identity-pools describe "$pool_id" \
        --location="$region" \
        --project="$PROJECT_ID" \
        &> /dev/null; then
        print_warning "Workload Identity Pool '$pool_id' already exists"
    else
        print_step "Creating Workload Identity Pool..."
        gcloud iam workload-identity-pools create "$pool_id" \
            --project="$PROJECT_ID" \
            --location="$region" \
            --display-name="GitHub Actions Pool" \
            --quiet
        print_success "Pool created: $pool_id"
    fi

    # Create provider
    if gcloud iam workload-identity-providers describe "$provider_id" \
        --location="$region" \
        --workload-identity-pool="$pool_id" \
        --project="$PROJECT_ID" \
        &> /dev/null; then
        print_warning "OIDC Provider '$provider_id' already exists"
    else
        print_step "Creating OIDC Provider..."
        gcloud iam workload-identity-providers create-oidc "$provider_id" \
            --project="$PROJECT_ID" \
            --location="$region" \
            --workload-identity-pool="$pool_id" \
            --display-name="GitHub" \
            --attribute-mapping="google.subject=assertion.sub,attribute.actor=assertion.actor,attribute.repository_owner=assertion.repository_owner" \
            --issuer-uri="https://token.actions.githubusercontent.com" \
            --attribute-condition="assertion.aud == '${PROJECT_ID}'" \
            --quiet
        print_success "OIDC Provider created: $provider_id"
    fi

    # Bind service account
    print_step "Binding GitHub OIDC token to service account..."
    gcloud iam service-accounts add-iam-policy-binding "$ci_sa" \
        --project="$PROJECT_ID" \
        --role="roles/iam.workloadIdentityUser" \
        --principal="principalSet://goog/github.com/${GITHUB_OWNER}/${GITHUB_REPO}/ref:refs/heads/main" \
        --quiet

    print_success "Service account binding created"

    # Output WIF provider resource name
    local wif_provider="projects/${PROJECT_NUMBER}/locations/${region}/workloadIdentityPools/${pool_id}/providers/${provider_id}"

    echo ""
    print_success "Workload Identity Federation configured"
    echo ""
    echo -e "${YELLOW}Save these values for GitHub Secrets:${NC}"
    echo ""
    echo -e "${BLUE}WIF_PROVIDER:${NC}"
    echo "  $wif_provider"
    echo ""
    echo -e "${BLUE}WIF_SERVICE_ACCOUNT:${NC}"
    echo "  $ci_sa"
    echo ""
}

# Create sample secrets
create_sample_secrets() {
    print_header "Creating Sample Secrets in Secret Manager"

    local sample_app_key=$(php -r 'echo "base64:" . base64_encode(random_bytes(32));' 2>/dev/null || echo "base64:YOUR_APP_KEY_HERE")
    local sample_jwt_secret=$(openssl rand -hex 32 2>/dev/null || echo "YOUR_64_CHAR_HEX_SECRET_HERE")

    print_warning "You need to provide actual values. These are placeholders."
    echo ""

    # Create or show existing secrets
    local secrets=(
        "app-key:$sample_app_key"
        "jwt-secret:$sample_jwt_secret"
        "db-host:10.0.0.5"
        "db-port:5432"
        "db-database:bekie_service"
        "db-username:bekie_user"
        "db-password:CHANGE_ME_TO_STRONG_PASSWORD"
        "db-sslmode:require"
        "redis-host:10.0.0.3"
        "redis-port:6379"
        "redis-password:CHANGE_ME_TO_STRONG_PASSWORD"
    )

    echo -e "${YELLOW}Execute these commands to create/update secrets:${NC}"
    echo ""

    for secret_pair in "${secrets[@]}"; do
        IFS=':' read -r name value <<< "$secret_pair"
        echo "gcloud secrets create $name --data-file=- --replication-policy='automatic' --project=$PROJECT_ID 2>/dev/null || echo -n '$value' | gcloud secrets versions add $name --data-file=- --project=$PROJECT_ID"
    done

    echo ""
    print_step "Replace placeholder values with your actual configuration"
}

# Generate output file
generate_output() {
    print_header "Generating Configuration Summary"

    local output_file="gcp-config.txt"

    cat > "$output_file" << EOF
╔════════════════════════════════════════════════════════════════════════════╗
║                   GOOGLE CLOUD CONFIGURATION SUMMARY                       ║
╚════════════════════════════════════════════════════════════════════════════╝

PROJECT INFORMATION
═══════════════════════════════════════════════════════════════════════════
Project ID:                   $PROJECT_ID
Project Number:               $PROJECT_NUMBER
Region:                       us-central1
Artifact Registry:            bekie-service

SERVICE ACCOUNTS
═══════════════════════════════════════════════════════════════════════════
Cloud Run:                    bekie-run@${PROJECT_ID}.iam.gserviceaccount.com
GitHub Actions CI/CD:         github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com

GITHUB CONFIGURATION
═══════════════════════════════════════════════════════════════════════════
Repository:                   ${GITHUB_OWNER}/${GITHUB_REPO}
Branch (for deployment):      main

GITHUB SECRETS (add to repository settings)
═══════════════════════════════════════════════════════════════════════════
WIF_PROVIDER:
  projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/github-pool/providers/github-provider

WIF_SERVICE_ACCOUNT:
  github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com

GITHUB VARIABLES (add to repository settings)
═══════════════════════════════════════════════════════════════════════════
GCP_PROJECT_ID:              $PROJECT_ID
GCP_REGION:                  us-central1
CLOUD_RUN_SERVICE_NAME:      bekie-service
APP_URL:                     https://api.yourdomain.com
LOG_CHANNEL:                 stderr
LOG_LEVEL:                   info
CACHE_STORE:                 redis
SESSION_DRIVER:              redis
QUEUE_CONNECTION:            redis
RUN_MIGRATIONS:              false (true for first deployment)
CLOUD_RUN_MEMORY:            512Mi
CLOUD_RUN_CPU:               1
CLOUD_RUN_TIMEOUT:           300
CLOUD_RUN_MAX_INSTANCES:     10
CLOUD_RUN_MIN_INSTANCES:     1

Secret References (GCP_SECRET_*):
  GCP_SECRET_APP_KEY:                  app-key:latest
  GCP_SECRET_DB_HOST:                  db-host:latest
  GCP_SECRET_DB_PORT:                  db-port:latest
  GCP_SECRET_DB_DATABASE:              db-database:latest
  GCP_SECRET_DB_USERNAME:              db-username:latest
  GCP_SECRET_DB_PASSWORD:              db-password:latest
  GCP_SECRET_DB_SSLMODE:               db-sslmode:latest
  GCP_SECRET_REDIS_HOST:               redis-host:latest
  GCP_SECRET_REDIS_PORT:               redis-port:latest
  GCP_SECRET_REDIS_PASSWORD:           redis-password:latest
  GCP_SECRET_JWT_SECRET:               jwt-secret:latest
  GCP_SECRET_CLOUDINARY_CLOUD_NAME:    cloudinary-cloud-name:latest
  GCP_SECRET_CLOUDINARY_API_KEY:       cloudinary-api-key:latest
  GCP_SECRET_CLOUDINARY_API_SECRET:    cloudinary-api-secret:latest

NEXT STEPS
═══════════════════════════════════════════════════════════════════════════
1. Set up Cloud SQL (PostgreSQL) and Cloud Memorystore (Redis)
2. Store secrets in Google Secret Manager (see GOOGLE_CLOUD_DEPLOYMENT.md)
3. Add GitHub Secrets and Variables listed above
4. Verify Dockerfile builds locally: docker build -t bekie-service:local .
5. Push a commit to main branch to trigger GitHub Actions
6. Monitor deployment in GitHub Actions and Cloud Run logs

For detailed instructions, see GOOGLE_CLOUD_DEPLOYMENT.md
EOF

    print_success "Configuration saved to: $output_file"
    cat "$output_file"
}

# Main execution
main() {
    print_header "Bekie Service - Google Cloud Setup"

    check_prerequisites
    get_project_info
    get_github_info

    # Confirm before proceeding
    echo ""
    print_warning "About to create resources in project: $PROJECT_ID"
    read -p "Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_error "Setup cancelled"
        exit 1
    fi

    enable_apis
    create_artifact_registry
    create_service_accounts
    grant_iam_roles
    setup_wif
    create_sample_secrets
    generate_output

    echo ""
    print_success "✓ Google Cloud setup completed!"
    echo ""
    print_warning "Important: Update secrets in Secret Manager and GitHub before deploying"
}

main "$@"

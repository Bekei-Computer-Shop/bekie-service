# Google Cloud Deployment Guide

This document describes how to deploy **Bekie Service** to Google Cloud using GitHub Actions CI/CD with Docker and Cloud Run.

---

## Table of Contents

1. [Architecture](#architecture)
2. [Prerequisites](#prerequisites)
3. [Google Cloud Setup](#google-cloud-setup)
4. [Workload Identity Federation (OIDC)](#workload-identity-federation-oidc)
5. [Secret Management](#secret-management)
6. [GitHub Configuration](#github-configuration)
7. [First Deployment](#first-deployment)
8. [Monitoring & Troubleshooting](#monitoring--troubleshooting)
9. [Rollback Procedure](#rollback-procedure)

---

## Architecture

```
GitHub Repository
    ↓
  [Push to main]
    ↓
GitHub Actions Workflow (.github/workflows/deploy.yml)
    ├─ Test & Lint (PHP + Node)
    ├─ Build Docker Image (multi-stage: Node/Vite → PHP/Laravel)
    ├─ Push to Google Artifact Registry
    └─ Deploy to Cloud Run
        ├─ Inject secrets from Google Secret Manager
        ├─ Run health check
        └─ Publish deployment summary
```

### Service Topology

```
Internet
    ↓
Cloud Run Load Balancer (managed, auto-scaling)
    ↓
bekie-service (Docker container)
    ├─ Apache 2.4 + PHP 8.2
    ├─ Connects to Cloud SQL (PostgreSQL)
    └─ Connects to Cloud Memorystore (Redis)
```

### Why Cloud Run?

- **Serverless** — no VM management, auto-scaling based on traffic
- **Stateless** — perfect fit for Laravel API with external DB/Redis
- **Cost-effective** — pay only for execution time + HTTP requests
- **Fast cold starts** — ~2-3 seconds, acceptable for API workload
- **Integrated security** — managed identity, automatic HTTPS

---

## Prerequisites

### Google Cloud

- **GCP Project** with billing enabled
- **gcloud CLI** installed locally (for setup only)
- **Editor** or higher IAM role on the project

### GitHub

- **Repository** on GitHub.com (public or private)
- **Admin access** to configure Secrets and Variables
- **main branch** protection enabled (recommended)

### Local Development

- Docker
- PHP 8.2 + Composer
- Node.js 20 + npm

---

## Google Cloud Setup

### 1. Create a GCP Project (if needed)

```bash
# Set your desired project name
PROJECT_NAME="bekie-service-prod"
PROJECT_ID="bekie-service-prod-$(date +%s | tail -c 6)"

# Create project
gcloud projects create $PROJECT_ID --name="$PROJECT_NAME"

# Set as default
gcloud config set project $PROJECT_ID

# Enable billing
gcloud billing projects link $PROJECT_ID --billing-account=<BILLING_ACCOUNT_ID>
```

### 2. Enable Required APIs

```bash
gcloud services enable \
  artifactregistry.googleapis.com \
  run.googleapis.com \
  cloudbuild.googleapis.com \
  sqladmin.googleapis.com \
  redis.googleapis.com \
  secretmanager.googleapis.com \
  iam.googleapis.com \
  iamcredentials.googleapis.com \
  sts.googleapis.com
```

### 3. Create Artifact Registry Repository

```bash
# Create repository for Docker images
gcloud artifacts repositories create bekie-service \
  --location=us-central1 \
  --repository-format=docker \
  --description="Bekie Service API Docker images"

# Verify
gcloud artifacts repositories list
```

### 4. Create Service Account for Cloud Run

```bash
# Create dedicated service account
gcloud iam service-accounts create bekie-run \
  --display-name="Bekie Service Cloud Run Account"

# Get full email for later use
gcloud iam service-accounts list --filter="name:bekie-run" --format="value(email)"
# Output: bekie-run@PROJECT_ID.iam.gserviceaccount.com
```

### 5. Grant Required Roles to Service Account

```bash
PROJECT_ID=$(gcloud config get-value project)
SERVICE_ACCOUNT_EMAIL="bekie-run@${PROJECT_ID}.iam.gserviceaccount.com"

# Basic Cloud Run permissions
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$SERVICE_ACCOUNT_EMAIL" \
  --role="roles/run.invoker"

# Allow to pull images from Artifact Registry
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$SERVICE_ACCOUNT_EMAIL" \
  --role="roles/artifactregistry.reader"

# Allow to access secrets
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$SERVICE_ACCOUNT_EMAIL" \
  --role="roles/secretmanager.secretAccessor"
```

### 6. Create Service Account for GitHub Actions (CI/CD)

```bash
# Create CI/CD service account
gcloud iam service-accounts create github-actions-ci \
  --display-name="GitHub Actions CI/CD"

# Get email
gcloud iam service-accounts list --filter="name:github-actions-ci" --format="value(email)"
# Output: github-actions-ci@PROJECT_ID.iam.gserviceaccount.com
```

### 7. Grant Permissions to GitHub Actions Service Account

```bash
PROJECT_ID=$(gcloud config get-value project)
CI_SERVICE_ACCOUNT_EMAIL="github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com"

# Push to Artifact Registry
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$CI_SERVICE_ACCOUNT_EMAIL" \
  --role="roles/artifactregistry.writer"

# Deploy to Cloud Run
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$CI_SERVICE_ACCOUNT_EMAIL" \
  --role="roles/run.developer"

# Manage Cloud Run services
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$CI_SERVICE_ACCOUNT_EMAIL" \
  --role="roles/run.admin"

# Pass service account (for `--service-account` flag)
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$CI_SERVICE_ACCOUNT_EMAIL" \
  --role="roles/iam.serviceAccountUser"
```

---

## Workload Identity Federation (OIDC)

This section sets up secure, keyless authentication from GitHub Actions to Google Cloud.

### 1. Configure OIDC Provider

```bash
PROJECT_ID=$(gcloud config get-value project)
WORKLOAD_IDENTITY_POOL_ID="github-pool"
WORKLOAD_IDENTITY_PROVIDER_ID="github-provider"
WORKLOAD_IDENTITY_POOL_LOCATION="global"

# Create Workload Identity Pool
gcloud iam workload-identity-pools create $WORKLOAD_IDENTITY_POOL_ID \
  --project=$PROJECT_ID \
  --location=$WORKLOAD_IDENTITY_POOL_LOCATION \
  --display-name="GitHub Actions Pool"

# Create OIDC Provider
gcloud iam workload-identity-providers create-oidc $WORKLOAD_IDENTITY_PROVIDER_ID \
  --project=$PROJECT_ID \
  --location=$WORKLOAD_IDENTITY_POOL_LOCATION \
  --workload-identity-pool=$WORKLOAD_IDENTITY_POOL_ID \
  --display-name="GitHub" \
  --attribute-mapping="google.subject=assertion.sub,attribute.actor=assertion.actor,attribute.repository_owner=assertion.repository_owner" \
  --issuer-uri="https://token.actions.githubusercontent.com" \
  --attribute-condition="assertion.aud == '${PROJECT_ID}'"
```

### 2. Get the Workload Identity Provider Resource Name

```bash
PROJECT_ID=$(gcloud config get-value project)
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")

echo "projects/${PROJECT_NUMBER}/locations/global/workloadIdentityPools/github-pool/providers/github-provider"
```

Save this value — you'll need it for GitHub Secrets.

### 3. Bind GitHub OIDC Token to Service Account

```bash
PROJECT_ID=$(gcloud config get-value project)
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format="value(projectNumber)")
CI_SERVICE_ACCOUNT_EMAIL="github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com"
WORKLOAD_IDENTITY_POOL_ID="github-pool"
WORKLOAD_IDENTITY_PROVIDER_ID="github-provider"

# Get GitHub repo owner and name
REPO_OWNER="your-github-username"  # Change this
REPO_NAME="sana-project"             # Change this if different

gcloud iam service-accounts add-iam-policy-binding $CI_SERVICE_ACCOUNT_EMAIL \
  --project=$PROJECT_ID \
  --role="roles/iam.workloadIdentityUser" \
  --principal="principalSet://goog/github.com/${REPO_OWNER}/${REPO_NAME}"
```

Alternatively, to be more restrictive (only main branch):

```bash
gcloud iam service-accounts add-iam-policy-binding $CI_SERVICE_ACCOUNT_EMAIL \
  --project=$PROJECT_ID \
  --role="roles/iam.workloadIdentityUser" \
  --principal="principalSet://goog/github.com/${REPO_OWNER}/${REPO_NAME}/ref:refs/heads/main"
```

---

## Secret Management

### Store Secrets in Google Secret Manager

Store sensitive configuration in Secret Manager so they're not committed to the repository.

```bash
PROJECT_ID=$(gcloud config get-value project)

# Function to create or update secret
create_or_update_secret() {
  local secret_name=$1
  local secret_value=$2

  if gcloud secrets describe $secret_name --project=$PROJECT_ID &>/dev/null; then
    echo "Updating secret: $secret_name"
    echo -n "$secret_value" | gcloud secrets versions add $secret_name \
      --data-file=- \
      --project=$PROJECT_ID
  else
    echo "Creating secret: $secret_name"
    echo -n "$secret_value" | gcloud secrets create $secret_name \
      --data-file=- \
      --replication-policy="automatic" \
      --project=$PROJECT_ID
  fi
}

# Create secrets (replace with your actual values)
create_or_update_secret "app-key" "base64:your-laravel-app-key-here"
create_or_update_secret "jwt-secret" "your-64-char-hex-jwt-secret"

# Database secrets (from Cloud SQL)
create_or_update_secret "db-host" "10.0.0.5"  # Cloud SQL private IP
create_or_update_secret "db-port" "5432"
create_or_update_secret "db-database" "bekie_service"
create_or_update_secret "db-username" "bekie_user"
create_or_update_secret "db-password" "strong-db-password"
create_or_update_secret "db-sslmode" "require"

# Redis secrets (from Cloud Memorystore)
create_or_update_secret "redis-host" "10.0.0.3"  # Cloud Memorystore private IP
create_or_update_secret "redis-port" "6379"
create_or_update_secret "redis-password" "strong-redis-password"

# Cloudinary secrets (if using)
create_or_update_secret "cloudinary-cloud-name" "your-cloudinary-name"
create_or_update_secret "cloudinary-api-key" "your-api-key"
create_or_update_secret "cloudinary-api-secret" "your-api-secret"

# List all secrets
gcloud secrets list --project=$PROJECT_ID
```

**Important**: The Cloud Run service account (`bekie-run`) needs `secretmanager.secretAccessor` role to read these. This was granted in step 5 above.

---

## GitHub Configuration

### 1. Set GitHub Secrets

In your GitHub repository, go to **Settings → Secrets and variables → Actions**.

Add these **Secrets** (encrypted, scoped to repo):

```
WIF_PROVIDER
  → projects/YOUR_PROJECT_NUMBER/locations/global/workloadIdentityPools/github-pool/providers/github-provider

WIF_SERVICE_ACCOUNT
  → github-actions-ci@YOUR_PROJECT_ID.iam.gserviceaccount.com
```

### 2. Set GitHub Variables

In **Settings → Secrets and variables → Actions → Variables**, add:

```
GCP_PROJECT_ID
  → bekie-service-prod-123456

GCP_REGION
  → us-central1

CLOUD_RUN_SERVICE_NAME
  → bekie-service

APP_URL
  → https://api.yourdomain.com

LOG_CHANNEL
  → stderr

LOG_LEVEL
  → info

CACHE_STORE
  → redis

SESSION_DRIVER
  → redis

QUEUE_CONNECTION
  → redis

RUN_MIGRATIONS
  → false (set to true for first deployment only)

CLOUD_RUN_MEMORY
  → 512Mi

CLOUD_RUN_CPU
  → 1

CLOUD_RUN_TIMEOUT
  → 300

CLOUD_RUN_MAX_INSTANCES
  → 10

CLOUD_RUN_MIN_INSTANCES
  → 1

# Secret Manager secret references (format: secret-name:version or secret-name:latest)
GCP_SECRET_APP_KEY
  → app-key:latest

GCP_SECRET_DB_HOST
  → db-host:latest

GCP_SECRET_DB_PORT
  → db-port:latest

GCP_SECRET_DB_DATABASE
  → db-database:latest

GCP_SECRET_DB_USERNAME
  → db-username:latest

GCP_SECRET_DB_PASSWORD
  → db-password:latest

GCP_SECRET_DB_SSLMODE
  → db-sslmode:latest

GCP_SECRET_REDIS_HOST
  → redis-host:latest

GCP_SECRET_REDIS_PORT
  → redis-port:latest

GCP_SECRET_REDIS_PASSWORD
  → redis-password:latest

GCP_SECRET_JWT_SECRET
  → jwt-secret:latest

GCP_SECRET_CLOUDINARY_CLOUD_NAME
  → cloudinary-cloud-name:latest

GCP_SECRET_CLOUDINARY_API_KEY
  → cloudinary-api-key:latest

GCP_SECRET_CLOUDINARY_API_SECRET
  → cloudinary-api-secret:latest
```

---

## First Deployment

### Prerequisites

- ✓ GCP Project created and APIs enabled
- ✓ Artifact Registry repository created
- ✓ Service accounts created with proper IAM roles
- ✓ Workload Identity Federation configured
- ✓ Secrets stored in Google Secret Manager
- ✓ GitHub Secrets and Variables configured
- ✓ Cloud SQL PostgreSQL and Cloud Memorystore Redis instances running

### Step 1: Verify Local Build

```bash
# Build the Docker image locally first
docker build -t bekie-service:local .

# Run locally with test configuration
docker run --rm \
  -e APP_ENV=local \
  -e APP_DEBUG=true \
  -e APP_KEY=base64:your-test-key \
  -e DB_CONNECTION=sqlite \
  -e CACHE_STORE=array \
  -p 8080:8080 \
  bekie-service:local
```

### Step 2: Push a Test Commit to main

The GitHub Actions workflow will automatically trigger:

```bash
# Make a small change or create a new commit
git add .
git commit -m "feat: enable Google Cloud CI/CD deployment"
git push origin main
```

### Step 3: Monitor the Workflow

1. Go to **GitHub → Actions**
2. Click on the latest workflow run
3. Watch the three jobs:
   - **Test & Lint** — runs tests against test PostgreSQL
   - **Build & Push** — builds Docker image and pushes to Artifact Registry
   - **Deploy** — deploys to Cloud Run and runs health check

### Step 4: Verify Deployment

Once the workflow completes:

```bash
PROJECT_ID=$(gcloud config get-value project)

# Check Cloud Run service
gcloud run services list --region=us-central1 --project=$PROJECT_ID

# Get the service URL
gcloud run services describe bekie-service \
  --region=us-central1 \
  --format='value(status.url)' \
  --project=$PROJECT_ID

# Test the health endpoint
curl -i https://bekie-service-RANDOM.run.app/health

# Check logs
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --limit=50 \
  --project=$PROJECT_ID
```

---

## Monitoring & Troubleshooting

### Check Workflow Logs

```bash
# GitHub Actions logs (in GitHub UI) show:
# - Test failures
# - Build errors
# - Push/authentication issues
# - Deployment commands
```

### Check Cloud Run Logs

```bash
# Real-time logs
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --tail \
  --limit=100

# Filter by severity
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --limit=50 \
  --filter="severity>=ERROR"
```

### Check Artifact Registry

```bash
# List all images
gcloud artifacts docker images list us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service

# List tags for an image
gcloud artifacts docker images list \
  us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service/bekie-service \
  --include-tags
```

### Common Issues

#### 1. Deployment fails with "Permission denied" on secrets

**Problem**: Cloud Run service account can't read Secret Manager secrets.

**Solution**:
```bash
PROJECT_ID=$(gcloud config get-value project)
SERVICE_ACCOUNT_EMAIL="bekie-run@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member="serviceAccount:$SERVICE_ACCOUNT_EMAIL" \
  --role="roles/secretmanager.secretAccessor"
```

#### 2. Workflow fails with "Invalid resource" when deploying

**Problem**: Secret references format is incorrect.

**Solution**: Use format `secret-name:latest` (not `secret-name` or `project/secret-name`).

#### 3. OIDC authentication fails in GitHub Actions

**Problem**: `WIF_PROVIDER` or `WIF_SERVICE_ACCOUNT` not set correctly.

**Solution**:
```bash
# Verify WIF setup
gcloud iam workload-identity-pools list --location=global

# Check service account binding
gcloud iam service-accounts get-iam-policy $CI_SERVICE_ACCOUNT_EMAIL
```

#### 4. Database connection fails at startup

**Problem**: Cloud Run can't reach Cloud SQL.

**Solution**:
- Ensure Cloud SQL has Cloud Run VPC connector or private IP
- Verify `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` in Secret Manager
- Check Cloud SQL proxy settings if using Compute Engine

---

## Rollback Procedure

### Rollback to Previous Image

The GitHub Actions workflow automatically tags images with both short SHA and `latest`. To rollback:

```bash
PROJECT_ID=$(gcloud config get-value project)
PREVIOUS_IMAGE="us-central1-docker.pkg.dev/${PROJECT_ID}/bekie-service/bekie-service:PREVIOUS_SHA"

# Redeploy the previous image
gcloud run deploy bekie-service \
  --image $PREVIOUS_IMAGE \
  --region=us-central1 \
  --project=$PROJECT_ID
```

**Alternative**: Use Cloud Run's revision history:

```bash
# List recent revisions
gcloud run revisions list --service=bekie-service --region=us-central1

# Route 100% traffic to a specific revision
gcloud run services update-traffic bekie-service \
  --to-revisions REVISION_NAME=100 \
  --region=us-central1
```

### How to Find Previous Image SHA

Check GitHub Actions history or Artifact Registry:

```bash
# In Artifact Registry, see all images with tags
gcloud artifacts docker images list \
  us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service/bekie-service \
  --include-tags | head -10
```

---

## Cost Optimization

### Reduce Cloud Run Costs

1. **Min instances**: Set `CLOUD_RUN_MIN_INSTANCES=0` to scale to zero during off-hours
2. **Memory**: Start with `256Mi` or `512Mi` instead of `1Gi`
3. **CPU**: Use `0.5` or `1` vCPU (not `2` or `4`)
4. **Timeout**: Keep `CLOUD_RUN_TIMEOUT=300` (5 min is standard)

### Monitor Spending

```bash
# View Cloud Run cost estimates
gcloud billing projects list

# Set up budget alerts
gcloud billing budgets create \
  --billing-account=BILLING_ACCOUNT_ID \
  --display-name="Bekie Service Budget" \
  --budget-amount=100 \
  --alert-threshold-percentages=50,100
```

---

## Production Checklist

Before going live:

- [ ] Cloud SQL PostgreSQL instance is running and accessible
- [ ] Cloud Memorystore Redis is running and accessible
- [ ] All secrets are created in Secret Manager
- [ ] GitHub Secrets and Variables are configured
- [ ] Workload Identity Federation is set up and tested
- [ ] Dockerfile builds successfully locally
- [ ] Tests pass in GitHub Actions
- [ ] First deployment succeeds (watch logs carefully)
- [ ] Health endpoint responds (e.g., `GET /health`)
- [ ] API endpoints work with real database
- [ ] Logs appear in Cloud Run logs
- [ ] Rollback procedure is documented and tested

---

## Support & Debugging

### Enable Debug Logging

Temporarily increase log level for troubleshooting:

```bash
# Update CLOUD_RUN_MIN_INSTANCES variable to trigger redeployment
# Change LOG_LEVEL variable to "debug"
```

Commit and push to trigger a new workflow run with debug logging.

### Contact Google Cloud Support

For infrastructure issues with Cloud SQL, Redis, Cloud Run:
- Visit [Google Cloud Console](https://console.cloud.google.com)
- Navigate to **Support → Create Ticket**
- Include error messages and service logs


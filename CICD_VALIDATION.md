# CI/CD Pipeline Validation Checklist

This document provides a step-by-step validation guide to ensure the GitHub Actions CI/CD pipeline is correctly configured before the first production deployment.

## Pre-Deployment Validation

### 1. GitHub Actions Workflow Syntax

**Validation**: YAML file is valid and follows GitHub Actions specification.

```bash
# Check YAML syntax locally
cd .github/workflows
yamllint deploy.yml  # requires yamllint: npm install -g yamllint
```

**Expected Output**: No errors

**Manual Check**: Go to **GitHub → Actions → Deployments** and verify no YAML syntax errors in the log.

---

### 2. Docker Image Build

**Validation**: Dockerfile builds successfully without errors.

```bash
# Build locally
docker build -t bekie-service:test .

# Expected: Successfully tagged bekie-service:test
```

**What to verify**:
- ✓ Node.js dependencies install successfully (`npm ci`)
- ✓ Vite build completes without errors (`npm run build`)
- ✓ PHP dependencies install successfully (`composer install`)
- ✓ Laravel permissions are set correctly
- ✓ Image final size is reasonable (~500-800MB)

**Check image size**:
```bash
docker images | grep bekie-service
# Expected output: bekie-service   test      XXXXX    XXX MB
```

---

### 3. Container Startup

**Validation**: Container starts successfully and listens on port 8080.

```bash
# Create test .env file
cp .env.example .env.test
sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' .env.test
sed -i 's/LOG_CHANNEL=.*/LOG_CHANNEL=stderr/' .env.test

# Run container
docker run \
  --rm \
  -e APP_ENV=production \
  -e APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));') \
  -e DB_CONNECTION=sqlite \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e LOG_LEVEL=info \
  -p 8080:8080 \
  bekie-service:test

# In another terminal, test health endpoint
sleep 5
curl -i http://localhost:8080/

# Expected: HTTP response (200 OK or 404 from missing route)
```

**What to verify**:
- ✓ Container starts without crashing (no "exited with code 1" errors)
- ✓ Apache is listening on port 8080
- ✓ PHP processes are running
- ✓ No permission errors in startup

---

### 4. Database Connectivity

**Validation**: Application can connect to PostgreSQL during initialization.

```bash
# Start PostgreSQL container
docker run -d \
  --name test-db \
  -e POSTGRES_DB=bekie_test \
  -e POSTGRES_USER=root \
  -e POSTGRES_PASSWORD=password \
  -p 5432:5432 \
  postgres:15

# Wait for database to be ready
sleep 10

# Run Laravel migrations
docker run \
  --rm \
  --network host \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=127.0.0.1 \
  -e DB_PORT=5432 \
  -e DB_DATABASE=bekie_test \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=password \
  -e APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));') \
  bekie-service:test \
  php artisan migrate --force

# Expected: "Migration table created successfully" and table migrations listed
```

---

### 5. Environment Variables & Secrets

**Validation**: Environment variables are correctly injected and secrets are accessible.

**In GitHub Actions**:
- [ ] All variables listed in `.github/workflows/deploy.yml` are defined in GitHub Variables
- [ ] All secrets are defined in GitHub Secrets (WIF_PROVIDER, WIF_SERVICE_ACCOUNT)
- [ ] No plain credentials are in the workflow file
- [ ] Secret references use format `secret-name:latest` in Cloud Run deployment

**Check workflow file**:
```bash
grep -E "secrets\.|vars\." .github/workflows/deploy.yml | head -20
```

**Expected output**: 
- Uses `${{ vars.XXX }}` for non-sensitive configuration
- Uses `${{ secrets.XXX }}` only for WIF authentication
- Uses `--set-secrets` for Secret Manager references

---

### 6. Docker Layer Caching

**Validation**: Dockerfile uses multi-stage build correctly for efficient caching.

```bash
# Build twice (second should be faster)
time docker build -t bekie-service:v1 .
time docker build -t bekie-service:v2 .

# Second build should be significantly faster (within 30-60 sec)
# if using cache from first build
```

**Expected behavior**:
- First stage (Node/Vite): 2-3 minutes
- Second stage (PHP/Laravel): 3-5 minutes
- Subsequent builds with no dependency changes: <30 seconds

---

### 7. GitHub Actions Test Job

**Validation**: Test job runs successfully and catches issues.

**Manual run**:
```bash
git add .github/workflows/deploy.yml
git commit -m "chore: add CI/CD pipeline"
git push origin main
```

**Expected workflow steps**:
1. Checkout code
2. Set up PHP 8.2
3. Set up Node 20
4. Cache composer packages
5. Install npm dependencies
6. Build assets
7. Run Pint linter (may show warnings)
8. Run tests against test PostgreSQL

**If tests fail**:
- Check the workflow log in GitHub Actions
- Look for database connection errors
- Verify test database is properly initialized
- Run tests locally: `php artisan test`

---

### 8. Artifact Registry Access

**Validation**: GitHub Actions can authenticate to Google Artifact Registry.

**Check permissions**:
```bash
PROJECT_ID=$(gcloud config get-value project)
CI_SA="github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com"

gcloud projects get-iam-policy $PROJECT_ID \
  --flatten="bindings[].members" \
  --filter="bindings.members:$CI_SA" \
  --format="table(bindings.role)"

# Expected roles:
# - roles/artifactregistry.writer
# - roles/run.admin
# - roles/iam.serviceAccountUser
```

---

### 9. Cloud Run Service Configuration

**Validation**: Cloud Run service exists and is configured correctly.

```bash
PROJECT_ID=$(gcloud config get-value project)
REGION=us-central1

# Check if service exists
gcloud run services describe bekie-service \
  --region=$REGION \
  --project=$PROJECT_ID

# Expected output: Service details including memory, CPU, timeout
```

**If service doesn't exist yet**: It will be created by the first deployment workflow run.

---

### 10. Secret Manager Secrets

**Validation**: All required secrets exist and are accessible to Cloud Run.

```bash
PROJECT_ID=$(gcloud config get-value project)

# List all secrets
gcloud secrets list --project=$PROJECT_ID

# Verify Cloud Run service account can read secrets
gcloud secrets get-iam-policy app-key --project=$PROJECT_ID
```

**Expected secrets**:
- `app-key`
- `jwt-secret`
- `db-host`, `db-port`, `db-database`, `db-username`, `db-password`, `db-sslmode`
- `redis-host`, `redis-port`, `redis-password`
- `cloudinary-cloud-name`, `cloudinary-api-key`, `cloudinary-api-secret` (if using)

---

## First Deployment Walkthrough

### Step 1: Trigger Workflow

```bash
# Ensure on main branch
git checkout main

# Make a small change (or use --allow-empty for testing)
git commit --allow-empty -m "feat: trigger initial Google Cloud deployment"
git push origin main
```

### Step 2: Monitor GitHub Actions

1. Go to **GitHub Repository → Actions**
2. Click on the latest workflow run
3. Watch each job:

**Job: Test & Lint**
- Should take 3-5 minutes
- Must pass all tests
- If fails: review test logs and fix issues locally

**Job: Build & Push**
- Depends on Test & Lint passing
- Should take 5-10 minutes
- Logs will show:
  - Docker build progress
  - Layer caching information
  - Push to Artifact Registry completion
- If fails: check Docker build errors and verify Artifact Registry access

**Job: Deploy**
- Depends on Build & Push passing
- Should take 2-3 minutes
- Logs will show:
  - Cloud Run deployment command
  - Service update progress
  - Health check results
- If fails: check Secret Manager access and Cloud Run configuration

### Step 3: Verify Cloud Run Deployment

```bash
# Get service URL
SERVICE_URL=$(gcloud run services describe bekie-service \
  --region=us-central1 \
  --format='value(status.url)' \
  --project=$PROJECT_ID)

echo "Service deployed at: $SERVICE_URL"

# Test API endpoint (will vary based on your routes)
curl -i "$SERVICE_URL/api/v1/health"

# Expected: 200 OK or 404 Not Found (depending on if route exists)
# Do NOT expect 500 errors
```

### Step 4: Check Cloud Run Logs

```bash
# Stream logs in real-time
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --tail=50 \
  --limit=100

# Filter for errors
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --filter="severity>=ERROR"
```

**Expected log patterns**:
- Service startup messages
- Request logs (GET /api/v1/...)
- No database connection errors (these indicate secret/network issues)

### Step 5: Database Migrations (If RUN_MIGRATIONS=true)

If you set `RUN_MIGRATIONS=true` in GitHub Variables for the first deployment:

```bash
# Check migration logs
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --filter="text:migrate"

# Expected: "Migrated: ..... (Xxx.xxms)"
```

After first deployment, set `RUN_MIGRATIONS=false` to prevent accidental migrations on every deploy.

---

## Security Validation

### 1. No Secrets in Logs

**Check**:
```bash
# Ensure no actual secrets appear in workflow logs
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --filter="text:(password|secret|key)" \
  --limit=50
```

**Expected**: No sensitive values visible in logs (redacted by Cloud Run)

### 2. Workload Identity Federation

**Verify OIDC configuration**:
```bash
# List OIDC providers
gcloud iam workload-identity-providers list \
  --location=global \
  --project=$PROJECT_ID

# Verify GitHub binding
gcloud iam service-accounts get-iam-policy \
  github-actions-ci@${PROJECT_ID}.iam.gserviceaccount.com
```

**Expected**: 
- OIDC provider for GitHub exists
- Service account has `roles/iam.workloadIdentityUser` binding

### 3. No Long-Lived Credentials

**Check GitHub Secrets**:
- [ ] Only `WIF_PROVIDER` and `WIF_SERVICE_ACCOUNT` (no JSON key files)
- [ ] No service account keys stored anywhere
- [ ] All authentication via OIDC tokens

---

## Performance Validation

### 1. Build Time

**Baseline**: Track build times for future optimization.

```bash
# Record time from GitHub Actions workflow
# Test & Lint:  ~3-5 min
# Build & Push: ~5-10 min
# Deploy:       ~2-3 min
# Total:        ~10-18 min
```

### 2. Cold Start Time

**After deployment**:
```bash
time curl -i $SERVICE_URL/health

# Expected: <3 seconds
# If >10 seconds: may indicate memory constraints
```

### 3. Deployment Downtime

**Expected**: 0 seconds (Cloud Run manages rolling deployment)

### 4. Image Size

```bash
gcloud artifacts docker images describe \
  us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service/bekie-service:latest
```

**Expected**: 500-800 MB (reasonable for PHP + Node build stage)

---

## Rollback Validation

### Test Rollback Procedure

```bash
# Get list of previous images
gcloud artifacts docker images list \
  us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service/bekie-service \
  --include-tags

# Simulate rollback
gcloud run deploy bekie-service \
  --image us-central1-docker.pkg.dev/$PROJECT_ID/bekie-service/bekie-service:PREVIOUS_SHA \
  --region=us-central1

# Verify service still responds
curl -i $SERVICE_URL/health
```

**Expected**: Service rolls back within 1-2 minutes with zero downtime.

---

## Continuous Validation

After initial deployment, validate these on every code change:

### Before Each Push

```bash
# 1. Run tests locally
php artisan test

# 2. Build Docker image
docker build -t bekie-service:test .

# 3. Check code style
vendor/bin/pint --test --dirty
```

### After Each Push

```bash
# 1. Watch workflow complete
# GitHub → Actions → Latest run

# 2. Check service logs for errors
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --limit=50

# 3. Quick API health check
curl -i $SERVICE_URL/health
```

---

## Summary

When all checks above pass ✓, the CI/CD pipeline is production-ready and can be trusted to:
- ✓ Run automated tests on every push
- ✓ Build production Docker images
- ✓ Deploy securely with OIDC authentication
- ✓ Manage secrets safely
- ✓ Maintain zero-downtime deployments
- ✓ Enable quick rollbacks if needed


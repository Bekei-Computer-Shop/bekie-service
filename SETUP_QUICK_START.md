# Google Cloud Deployment - Quick Start Guide

A streamlined checklist to get Bekie Service deployed to Google Cloud in ~30 minutes.

## Prerequisites Checklist

- [ ] GCP Project created with billing enabled
- [ ] `gcloud` CLI installed locally
- [ ] GitHub admin access to the repository
- [ ] Docker Desktop (for local testing)

## Step 1: Run the Automated Setup Script (5 min)

### On Linux/macOS

```bash
cd bekie-service
chmod +x scripts/setup-google-cloud.sh
./scripts/setup-google-cloud.sh
```

### On Windows (PowerShell)

```powershell
# Install Ubuntu/WSL if not already done, or use Cloud Shell:
# Visit: https://console.cloud.google.com, click ">_" button (Cloud Shell)
# Then run the script above
```

The script will:
- Enable required Google Cloud APIs
- Create Artifact Registry repository
- Create service accounts with proper IAM roles
- Set up Workload Identity Federation (OIDC) for keyless authentication
- Output a configuration summary

**Save the output** — you'll need the `WIF_PROVIDER` and `WIF_SERVICE_ACCOUNT` values.

## Step 2: Create Secrets in Google Secret Manager (3 min)

Use [Google Cloud Console](https://console.cloud.google.com):

1. Go to **Secret Manager**
2. Click **Create Secret** and add these (replace with your actual values):

| Secret Name | Sample Value | Notes |
|---|---|---|
| `app-key` | `base64:xxxxx` | Generate: `php -r 'echo "base64:" . base64_encode(random_bytes(32));'` |
| `jwt-secret` | `64-char-hex` | Generate: `openssl rand -hex 32` |
| `db-host` | `10.0.0.5` | Private IP of Cloud SQL instance |
| `db-port` | `5432` | PostgreSQL port |
| `db-database` | `bekie_service` | Your database name |
| `db-username` | `bekie_user` | Database user |
| `db-password` | `strong-pwd` | Strong database password |
| `db-sslmode` | `require` | Always use SSL |
| `redis-host` | `10.0.0.3` | Private IP of Cloud Memorystore |
| `redis-port` | `6379` | Redis port |
| `redis-password` | `strong-pwd` | Redis password |
| `cloudinary-cloud-name` | `your-name` | From Cloudinary dashboard |
| `cloudinary-api-key` | `your-key` | From Cloudinary dashboard |
| `cloudinary-api-secret` | `your-secret` | From Cloudinary dashboard |

## Step 3: Create Google Cloud Infrastructure (5 min)

### Create Cloud SQL (PostgreSQL)

```bash
# Create instance
gcloud sql instances create bekie-postgres \
  --database-version=POSTGRES_15 \
  --tier=db-f1-micro \
  --region=us-central1

# Create database
gcloud sql databases create bekie_service \
  --instance=bekie-postgres

# Create user
gcloud sql users create bekie_user \
  --instance=bekie-postgres \
  --password=YOUR_STRONG_PASSWORD
```

### Create Cloud Memorystore (Redis)

```bash
gcloud redis instances create bekie-redis \
  --size=1 \
  --region=us-central1 \
  --redis-version=7.0
```

**Important**: Use VPC connector to connect Cloud Run → Cloud SQL/Redis over private network.

## Step 4: Configure GitHub (5 min)

### Add GitHub Secrets

Go to **Repository → Settings → Secrets and variables → Actions → Secrets**

Click **New repository secret** and add:

```
WIF_PROVIDER = <value from setup script output>
WIF_SERVICE_ACCOUNT = <value from setup script output>
```

### Add GitHub Variables

Go to **Settings → Secrets and variables → Actions → Variables**

Click **New repository variable** and add:

```
GCP_PROJECT_ID = bekie-service-prod-123456
GCP_REGION = us-central1
CLOUD_RUN_SERVICE_NAME = bekie-service
APP_URL = https://api.yourdomain.com
LOG_CHANNEL = stderr
LOG_LEVEL = info
CACHE_STORE = redis
SESSION_DRIVER = redis
QUEUE_CONNECTION = redis
RUN_MIGRATIONS = true
CLOUD_RUN_MEMORY = 512Mi
CLOUD_RUN_CPU = 1

GCP_SECRET_APP_KEY = app-key:latest
GCP_SECRET_JWT_SECRET = jwt-secret:latest
GCP_SECRET_DB_HOST = db-host:latest
GCP_SECRET_DB_PORT = db-port:latest
GCP_SECRET_DB_DATABASE = db-database:latest
GCP_SECRET_DB_USERNAME = db-username:latest
GCP_SECRET_DB_PASSWORD = db-password:latest
GCP_SECRET_DB_SSLMODE = db-sslmode:latest
GCP_SECRET_REDIS_HOST = redis-host:latest
GCP_SECRET_REDIS_PORT = redis-port:latest
GCP_SECRET_REDIS_PASSWORD = redis-password:latest
GCP_SECRET_CLOUDINARY_CLOUD_NAME = cloudinary-cloud-name:latest
GCP_SECRET_CLOUDINARY_API_KEY = cloudinary-api-key:latest
GCP_SECRET_CLOUDINARY_API_SECRET = cloudinary-api-secret:latest
```

## Step 5: Test the Workflow (10 min)

Push a test commit:

```bash
git add .
git commit -m "feat: enable Google Cloud CI/CD deployment"
git push origin main
```

Go to **GitHub → Actions** and watch:

1. **Test & Lint** job — runs PHP tests (should pass)
2. **Build & Push** job — builds Docker image and pushes to Artifact Registry
3. **Deploy** job — deploys to Cloud Run and verifies health

✓ If all jobs pass, deployment is complete!

## Verify Deployment

```bash
# Get Cloud Run URL
gcloud run services describe bekie-service \
  --region=us-central1 \
  --format='value(status.url)'

# Test API endpoint
curl -i https://bekie-service-RANDOM.run.app/api/v1/health
```

## Enable HTTPS Custom Domain (Optional)

```bash
# Map custom domain to Cloud Run service
gcloud run domain-mappings create \
  --service=bekie-service \
  --domain=api.yourdomain.com \
  --region=us-central1
```

Then add the DNS CNAME record provided in your domain registrar.

## Common Commands

### View Logs

```bash
# Real-time logs
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --tail

# Last 50 lines
gcloud run services logs read bekie-service \
  --region=us-central1 \
  --limit=50
```

### Rollback to Previous Version

```bash
# List revisions
gcloud run revisions list --service=bekie-service --region=us-central1

# Rollback (route traffic to specific revision)
gcloud run services update-traffic bekie-service \
  --to-revisions REVISION_NAME=100 \
  --region=us-central1
```

### Stop Auto-Deployment

Edit `.github/workflows/deploy.yml` and change:

```yaml
on:
  push:
    branches: [main]  # ← Remove this line or change branch
```

## Troubleshooting

### Tests fail in GitHub Actions

```bash
# Debug locally with same database
docker run -d \
  --name test-postgres \
  -e POSTGRES_PASSWORD=password \
  -p 5432:5432 \
  postgres:15

# Run tests locally
php artisan test --env=testing
```

### Deployment fails with "Permission denied"

1. Check WIF setup: `gcloud iam workload-identity-pools list --location=global`
2. Verify service account: `gcloud iam service-accounts get-iam-policy github-actions-ci@PROJECT_ID.iam.gserviceaccount.com`
3. Re-run setup script to fix permissions

### Cloud Run service doesn't connect to database

1. Check Cloud SQL VPC connector is configured on Cloud Run
2. Verify `DB_HOST` points to Cloud SQL private IP (10.x.x.x)
3. Test Cloud SQL network: `gcloud sql connect bekie-postgres --user=bekie_user`

## Next Steps

- [ ] Read [GOOGLE_CLOUD_DEPLOYMENT.md](GOOGLE_CLOUD_DEPLOYMENT.md) for detailed reference
- [ ] Set up monitoring/alerting in Cloud Console
- [ ] Configure Cloud CDN for API performance
- [ ] Enable Cloud Armor for DDoS protection
- [ ] Set up automated backups for Cloud SQL

## Support

For detailed setup instructions, troubleshooting, and cost optimization, see:
- **[GOOGLE_CLOUD_DEPLOYMENT.md](GOOGLE_CLOUD_DEPLOYMENT.md)** — comprehensive reference
- **[GitHub Actions Logs](https://github.com/your-repo/actions)** — workflow execution details
- **[Google Cloud Logs Explorer](https://console.cloud.google.com/logs)** — service logs


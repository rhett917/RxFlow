# RxFlow Quick Start Guide

## Prerequisites

- Ubuntu 20.04+ or macOS 10.15+
- PHP 8.1+
- R 4.0+
- MySQL 8.0+
- Redis 6.0+
- Docker & Docker Compose (optional)

## Option 1: Local Development Setup

### 1. Clone the repository
```bash
git clone https://github.com/your-repo/rxflow.git
cd rxflow
```

### 2. Run the setup script
```bash
./scripts/setup/setup-dev-environment.sh
```

This script will:
- Install all system dependencies (PHP, R, MySQL, Redis, Tesseract)
- Install R packages for prescription validation
- Install PHP dependencies via Composer
- Set up the database
- Create necessary directories

### 3. Configure environment
```bash
cp .env.example .env
nano .env  # Edit with your credentials
```

Key configurations:
- `DB_USERNAME` and `DB_PASSWORD` - MySQL credentials
- `BOTCONVERSA_TOKEN` - WhatsApp API token
- `MERCADOPAGO_ACCESS_TOKEN` - Payment gateway token

### 4. Run database migrations
```bash
php scripts/migration/create_tables.php
```

### 5. Start the development server
```bash
# Terminal 1: Start web server
php -S localhost:8080 -t public

# Terminal 2: Start queue worker
php src/workers/queue_worker.php

# Terminal 3: Start Redis (if not running)
redis-server
```

## Option 2: Docker Setup

### 1. Clone and configure
```bash
git clone https://github.com/your-repo/rxflow.git
cd rxflow
cp .env.example .env
nano .env  # Edit with your credentials
```

### 2. Build and run
```bash
docker-compose up --build
```

This will start:
- Web server on http://localhost:8080
- MySQL on localhost:3306
- Redis on localhost:6379
- Queue worker

## Testing the Installation

### 1. Check system health
```bash
curl http://localhost:8080/api/health
```

### 2. Test OCR with sample prescription
```bash
curl -X POST http://localhost:8080/api/prescriptions/test \
  -F "image=@data/prescriptions/typed/sample1.jpg"
```

### 3. Verify R integration
```bash
Rscript src/services/validation/validate_prescription.R '{"parsed_data":{"patient_name":"Test"},"confidence":0.9}'
```

## WhatsApp Integration Setup

1. Configure BotConversa webhook URL:
   ```
   https://your-domain.com/api/webhook/whatsapp
   ```

2. Test webhook:
   ```bash
   curl -X POST http://localhost:8080/api/webhook/whatsapp \
     -H "Content-Type: application/json" \
     -d '{"from":"5511999999999","message":{"type":"text","text":"ajuda"}}'
   ```

## Common Issues

### R packages installation fails
```bash
sudo R
> install.packages(c("jsonlite", "stringr", "dplyr", "stringdist"))
> q()
```

### Tesseract language packs
```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr-por

# macOS
brew install tesseract-lang
```

### Permission issues
```bash
sudo chown -R $USER:$USER .
chmod 755 public/uploads logs
```

## Development Workflow

1. **Add prescription samples** to `data/prescriptions/`
2. **Monitor logs** in `logs/` directory
3. **Run tests**: `composer test`
4. **Check code quality**: `composer analyse`

## Next Steps

- Read the [API Documentation](docs/API_DOCUMENTATION.md)
- Review the [Architecture Guide](docs/ARCHITECTURE.md)
- Check [Deployment Guide](docs/DEPLOYMENT.md) for production setup
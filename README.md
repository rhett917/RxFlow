# RxFlow - AI Prescription Processing System

AI-powered solution for reading medical prescriptions and integrating with Fórmula Certa ERP system.

## Features
- OCR for typed and handwritten prescriptions
- Automatic data extraction and validation
- ERP integration (Fórmula Certa)
- WhatsApp quote delivery
- Payment link generation

## Architecture
```
WhatsApp (BotConversa) → OCR (Tesseract/Vision API) → Validation (R/PHP) → ERP → Quote → Payment
```

## Quick Start
1. Clone repository
2. Copy `.env.example` to `.env` and configure
3. Run `./scripts/setup/install.sh`
4. Start services: `docker-compose up`

## Directory Structure
- `/src` - Application source code
- `/tests` - Test suites
- `/data` - Prescription samples and dictionaries
- `/docs` - Documentation
- `/deployment` - Production configurations
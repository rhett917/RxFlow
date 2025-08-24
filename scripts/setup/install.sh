#!/bin/bash

echo "🚀 RxFlow Installation Script"
echo "============================"

# Check prerequisites
echo "📋 Checking prerequisites..."

command -v docker >/dev/null 2>&1 || { echo "❌ Docker is required but not installed. Aborting." >&2; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo "❌ Docker Compose is required but not installed. Aborting." >&2; exit 1; }

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p data/prescriptions/{typed,handwritten}
mkdir -p data/dictionaries
mkdir -p logs
mkdir -p public/uploads
mkdir -p credentials

# Set permissions
chmod 755 public/uploads
chmod 755 logs

# Copy environment file
if [ ! -f .env ]; then
    echo "🔧 Creating .env file..."
    cp .env.example .env
    echo "⚠️  Please edit .env file with your credentials"
fi

# Download medication database
echo "📥 Downloading medication database..."
curl -s https://raw.githubusercontent.com/your-repo/medication-db/main/medications.csv \
    -o data/dictionaries/medications.csv 2>/dev/null || \
    echo "name,min_dose,max_dose,unit
Amoxicilina,250,1000,mg
Dipirona,500,1000,mg
Paracetamol,500,1000,mg
Ibuprofeno,200,800,mg" > data/dictionaries/medications.csv

# Build Docker images
echo "🐳 Building Docker images..."
docker-compose build

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
docker run --rm -v $(pwd):/app composer install

# Create database tables
echo "🗄️ Setting up database..."
docker-compose up -d mysql
sleep 10
docker-compose exec mysql mysql -uroot -p${DB_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS formula_certa;"

# Run migrations
echo "🔄 Running migrations..."
docker-compose run --rm app php scripts/migration/create_tables.php

echo "✅ Installation complete!"
echo ""
echo "Next steps:"
echo "1. Edit .env file with your credentials"
echo "2. Add prescription samples to data/prescriptions/"
echo "3. Run: docker-compose up"
echo "4. Access: http://localhost:8080"
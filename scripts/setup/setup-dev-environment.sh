#!/bin/bash

echo "🔧 Setting up RxFlow Development Environment"
echo "=========================================="

# Detect OS
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
    DISTRO=$(lsb_release -si 2>/dev/null || echo "Unknown")
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
else
    echo "❌ Unsupported OS: $OSTYPE"
    exit 1
fi

echo "📍 Detected OS: $OS ($DISTRO)"

# Install system dependencies
echo "📦 Installing system dependencies..."

if [ "$OS" == "linux" ]; then
    # Update package lists
    sudo apt-get update
    
    # Install basic tools
    sudo apt-get install -y \
        curl \
        wget \
        git \
        build-essential \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release
    
    # Install PHP 8.1
    echo "🐘 Installing PHP 8.1..."
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update
    sudo apt-get install -y \
        php8.1 \
        php8.1-cli \
        php8.1-fpm \
        php8.1-mysql \
        php8.1-xml \
        php8.1-mbstring \
        php8.1-curl \
        php8.1-gd \
        php8.1-zip \
        php8.1-redis
    
    # Install R
    echo "📊 Installing R..."
    # Add R repository
    sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys E298A3A825C0D65DFD57CBB651716619E084DAB9
    sudo add-apt-repository "deb https://cloud.r-project.org/bin/linux/ubuntu $(lsb_release -cs)-cran40/"
    sudo apt-get update
    sudo apt-get install -y r-base r-base-dev
    
    # Install Tesseract
    echo "👁️ Installing Tesseract OCR..."
    sudo apt-get install -y \
        tesseract-ocr \
        tesseract-ocr-por \
        libtesseract-dev \
        libleptonica-dev
    
    # Install Redis
    echo "💾 Installing Redis..."
    sudo apt-get install -y redis-server
    sudo systemctl enable redis-server
    sudo systemctl start redis-server
    
    # Install MySQL
    echo "🗄️ Installing MySQL..."
    sudo apt-get install -y mysql-server mysql-client
    sudo systemctl enable mysql
    sudo systemctl start mysql

elif [ "$OS" == "macos" ]; then
    # Check if Homebrew is installed
    if ! command -v brew &> /dev/null; then
        echo "🍺 Installing Homebrew..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    fi
    
    # Install dependencies via Homebrew
    echo "📦 Installing dependencies via Homebrew..."
    brew update
    brew install \
        php@8.1 \
        r \
        tesseract \
        tesseract-lang \
        redis \
        mysql \
        composer
    
    # Start services
    brew services start redis
    brew services start mysql
fi

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "🎼 Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Install R packages
echo "📚 Installing R packages..."
Rscript scripts/setup/install-r-packages.R

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "🔧 Creating .env file..."
    cp .env.example .env
    
    # Generate app key
    APP_KEY=$(openssl rand -base64 32)
    sed -i.bak "s/APP_KEY=/APP_KEY=$APP_KEY/" .env
    
    echo "⚠️  Please update .env with your credentials"
fi

# Set up database
echo "🗄️ Setting up database..."
read -p "Enter MySQL root password (press enter for none): " -s MYSQL_ROOT_PASS
echo

if [ -z "$MYSQL_ROOT_PASS" ]; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS formula_certa;"
    mysql -u root -e "CREATE USER IF NOT EXISTS 'rxflow'@'localhost' IDENTIFIED BY 'rxflow123';"
    mysql -u root -e "GRANT ALL PRIVILEGES ON formula_certa.* TO 'rxflow'@'localhost';"
else
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS formula_certa;"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "CREATE USER IF NOT EXISTS 'rxflow'@'localhost' IDENTIFIED BY 'rxflow123';"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "GRANT ALL PRIVILEGES ON formula_certa.* TO 'rxflow'@'localhost';"
fi

# Create directories
echo "📁 Creating project directories..."
mkdir -p data/prescriptions/{typed,handwritten}
mkdir -p logs
mkdir -p public/uploads
mkdir -p credentials

# Set permissions
chmod 755 public/uploads
chmod 755 logs

# Test installations
echo ""
echo "✅ Verifying installations:"
echo "============================"
php -v | head -n 1
echo "R version: $(R --version | head -n 1)"
echo "Tesseract version: $(tesseract --version 2>&1 | head -n 1)"
echo "Redis: $(redis-cli --version)"
echo "MySQL: $(mysql --version)"
echo "Composer: $(composer --version)"

echo ""
echo "🎉 Development environment setup complete!"
echo ""
echo "Next steps:"
echo "1. Update .env file with your actual credentials"
echo "2. Run database migrations: php scripts/migration/create_tables.php"
echo "3. Start development server: php -S localhost:8080 -t public"
echo "4. In another terminal, start queue worker: php src/workers/queue_worker.php"
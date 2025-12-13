#!/bin/bash

# AI Suite Deployment Script
# This script helps deploy the AI Suite application to a server

set -e

echo "🚀 AI Suite Deployment Script"
echo "=============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo -e "${RED}❌ Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

# Use docker compose (v2) if available, otherwise docker-compose (v1)
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

echo -e "${GREEN}✓ Docker and Docker Compose are installed${NC}"

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠ .env file not found. Creating from .env.example...${NC}"
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${YELLOW}⚠ Please edit .env file and set your configuration values${NC}"
        echo -e "${YELLOW}⚠ Especially important: APP_KEY, DB_PASSWORD, and API keys${NC}"
        read -p "Press Enter after you've configured .env file..."
    else
        echo -e "${RED}❌ .env.example file not found. Please create .env manually.${NC}"
        exit 1
    fi
fi

# Check for required environment variables
echo -e "${YELLOW}🔍 Checking required environment variables...${NC}"
source .env 2>/dev/null || true

if [ -z "$DB_PASSWORD" ]; then
    echo -e "${RED}❌ DB_PASSWORD is not set in .env file${NC}"
    echo -e "${YELLOW}⚠ Please set DB_PASSWORD in .env file before continuing${NC}"
    echo -e "${YELLOW}⚠ Example: DB_PASSWORD=your_strong_password_here${NC}"
    exit 1
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo -e "${YELLOW}⚠ APP_KEY is not set, will be generated...${NC}"
fi

# Generate APP_KEY if not set
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo -e "${YELLOW}⚠ Generating APP_KEY...${NC}"
    APP_KEY=$(docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env
    else
        # Linux
        sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env
    fi
    echo -e "${GREEN}✓ APP_KEY generated${NC}"
fi

# Pull latest images
echo -e "${YELLOW}📦 Pulling Docker images...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml pull

# Build application image
echo -e "${YELLOW}🔨 Building application image...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml build --no-cache

# Start services
echo -e "${YELLOW}🚀 Starting services...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml up -d

# Wait for database to be ready
echo -e "${YELLOW}⏳ Waiting for database to be ready...${NC}"
sleep 10

# Run migrations
echo -e "${YELLOW}📊 Running database migrations...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app php artisan migrate --force

# Clear and cache configuration
echo -e "${YELLOW}🧹 Optimizing application...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app php artisan config:cache
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app php artisan route:cache
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app php artisan view:cache

# Set permissions
echo -e "${YELLOW}🔐 Setting permissions...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app chown -R www-data:www-data /var/www/html/storage
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app chown -R www-data:www-data /var/www/html/bootstrap/cache
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app chmod -R 775 /var/www/html/storage
$DOCKER_COMPOSE -f docker-compose.prod.yml exec -T app chmod -R 775 /var/www/html/bootstrap/cache

# Check service status
echo -e "${YELLOW}📋 Checking service status...${NC}"
$DOCKER_COMPOSE -f docker-compose.prod.yml ps

echo ""
echo -e "${GREEN}✅ Deployment completed successfully!${NC}"
echo ""
echo "📝 Next steps:"
APP_PORT_VALUE=${APP_PORT:-8080}
echo "   1. Application is running on port: ${APP_PORT_VALUE}"
if [ "$APP_PORT_VALUE" != "80" ]; then
    echo "      (Port 80 is likely in use by another service)"
    echo "      Access at: http://YOUR_SERVER_IP:${APP_PORT_VALUE}"
    echo ""
    echo "   2. Configure LiteSpeed/Nginx/Apache as reverse proxy:"
    echo "      - Point proxy to: http://127.0.0.1:${APP_PORT_VALUE}"
    echo "      - See LITESPEED_SETUP.md for LiteSpeed configuration"
else
    echo "   1. Configure your reverse proxy (nginx/apache) to point to port 80"
fi
echo "   2. Set up SSL certificates (Let's Encrypt recommended)"
echo "   3. Configure your domain DNS to point to this server"
echo "   4. Update APP_URL in .env file"
echo ""
echo "🔧 Useful commands:"
echo "   - View logs: $DOCKER_COMPOSE -f docker-compose.prod.yml logs -f"
echo "   - Stop services: $DOCKER_COMPOSE -f docker-compose.prod.yml down"
echo "   - Restart services: $DOCKER_COMPOSE -f docker-compose.prod.yml restart"
echo "   - Run artisan commands: $DOCKER_COMPOSE -f docker-compose.prod.yml exec app php artisan <command>"
echo ""


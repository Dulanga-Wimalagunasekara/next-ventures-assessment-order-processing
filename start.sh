#!/bin/bash

# Order Processing System - Quick Start Script

echo "========================================="
echo "Order Processing System - Quick Start"
echo "========================================="
echo ""

# Check if Redis is running
echo "Checking Redis connection..."
if redis-cli ping > /dev/null 2>&1; then
    echo "✓ Redis is running"
else
    echo "✗ Redis is NOT running"
    echo ""
    echo "Please start Redis first:"
    echo "  macOS (Homebrew): brew services start redis"
    echo "  Linux: sudo systemctl start redis"
    echo "  Docker: docker run -d -p 6379:6379 redis:alpine"
    echo ""
    exit 1
fi

# Check if RabbitMQ is running (optional)
echo "Checking RabbitMQ connection (optional)..."
if nc -z localhost 5672 > /dev/null 2>&1; then
    echo "✓ RabbitMQ is running"
else
    echo "⚠ RabbitMQ is NOT running (using Redis queue instead)"
fi

echo ""
echo "========================================="
echo "Running Setup Steps"
echo "========================================="
echo ""

# Run migrations
echo "Running database migrations..."
php artisan migrate --force
echo "✓ Migrations complete"
echo ""

# Seed products
echo "Seeding products..."
php artisan db:seed --class=ProductSeeder
echo "✓ Products seeded"
echo ""

# Clear caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
echo "✓ Caches cleared"
echo ""

echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Start Horizon (in a new terminal):"
echo "   php artisan horizon"
echo ""
echo "2. Start the development server (in another terminal):"
echo "   php artisan serve"
echo ""
echo "3. Import orders:"
echo "   php artisan orders:import sample.csv"
echo ""
echo "4. View Horizon dashboard:"
echo "   http://localhost:8000/horizon"
echo ""
echo "5. Test API endpoints:"
echo "   curl http://localhost:8000/api/kpis/daily"
echo "   curl http://localhost:8000/api/leaderboard/customers"
echo ""


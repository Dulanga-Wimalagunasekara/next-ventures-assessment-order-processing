#!/bin/bash

# Test script for Order Processing System
# This script demonstrates all features

echo "========================================="
echo "Order Processing System - Test Suite"
echo "========================================="
echo ""

BASE_URL="http://localhost:8000"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print test results
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
    fi
}

# Function to make API call and check response
test_api() {
    local endpoint=$1
    local description=$2

    echo -n "Testing: $description... "
    response=$(curl -s -w "%{http_code}" -o /tmp/api_response.json "$BASE_URL$endpoint")
    http_code=${response: -3}

    if [ "$http_code" -eq 200 ] || [ "$http_code" -eq 201 ]; then
        echo -e "${GREEN}✓ (HTTP $http_code)${NC}"
        echo "Response:"
        cat /tmp/api_response.json | python3 -m json.tool 2>/dev/null || cat /tmp/api_response.json
        echo ""
        return 0
    else
        echo -e "${RED}✗ (HTTP $http_code)${NC}"
        cat /tmp/api_response.json
        echo ""
        return 1
    fi
}

echo "Step 1: Health Check"
echo "-----------------------------------"
test_api "/api/health" "System Health"
echo ""

echo "Step 2: System Statistics"
echo "-----------------------------------"
test_api "/api/stats" "System Stats"
echo ""

echo "Step 3: Daily KPIs"
echo "-----------------------------------"
test_api "/api/kpis/daily" "Today's KPIs"
echo ""

echo "Step 4: Real-time KPIs"
echo "-----------------------------------"
test_api "/api/kpis/realtime" "Real-time Metrics"
echo ""

echo "Step 5: Customer Leaderboard"
echo "-----------------------------------"
test_api "/api/leaderboard/customers?limit=5" "Top 5 Customers"
echo ""

echo "Step 6: Product Leaderboard"
echo "-----------------------------------"
test_api "/api/leaderboard/products?limit=5" "Top 5 Products"
echo ""

echo "Step 7: Refund System"
echo "-----------------------------------"
test_api "/api/refunds/stats" "Refund Statistics"
echo ""
# Note: Refund creation requires POST with JSON body, tested separately in demo-refunds.sh

echo "Step 8: Notification System"
echo "-----------------------------------"
test_api "/api/notifications/stats" "Notification Statistics"
echo ""
test_api "/api/notifications/recent?limit=5" "Recent Notifications"
echo ""

echo "Step 9: Database Check"
echo "-----------------------------------"
echo "Checking database records..."
php artisan tinker --execute="
echo 'Total Orders: ' . \App\Models\Order::count() . PHP_EOL;
echo 'Completed Orders: ' . \App\Models\Order::where('status', 'completed')->count() . PHP_EOL;
echo 'Pending Orders: ' . \App\Models\Order::where('status', 'pending')->count() . PHP_EOL;
echo 'Failed Orders: ' . \App\Models\Order::whereIn('status', ['failed', 'rollback'])->count() . PHP_EOL;
echo 'Total Products: ' . \App\Models\Product::count() . PHP_EOL;
echo 'Total Payments: ' . \App\Models\Payment::count() . PHP_EOL;
echo 'Stock Reservations: ' . \App\Models\StockReservation::count() . PHP_EOL;
echo 'Total Notifications: ' . \App\Models\Notification::count() . PHP_EOL;
echo 'Total Refunds: ' . \App\Models\Refund::count() . PHP_EOL;
"
echo ""

echo "Step 9: Redis Cache Check"
echo "-----------------------------------"
echo "Checking Redis keys..."
redis-cli KEYS "*kpis*" 2>/dev/null || echo "Redis not available or no KPI keys"
redis-cli KEYS "*leaderboard*" 2>/dev/null || echo "Redis not available or no leaderboard keys"
echo ""

echo "Step 10: Queue Status"
echo "-----------------------------------"
echo "Checking queue status..."
redis-cli LLEN "queues:orders" 2>/dev/null && echo "orders queue" || echo "Queue not found"
redis-cli LLEN "queues:default" 2>/dev/null && echo "default queue" || echo "Queue not found"
redis-cli LLEN "queues:notifications" 2>/dev/null && echo "notifications queue" || echo "Queue not found"
echo ""

echo "========================================="
echo "Test Suite Complete!"
echo "========================================="
echo ""
echo "To view more details:"
echo "  - Horizon Dashboard: $BASE_URL/horizon"
echo "  - API Documentation: See DEPLOYMENT.md"
echo "  - System Logs: tail -f storage/logs/laravel.log"
echo ""


# Laravel Order Processing System - Deployment Guide

## Overview
Complete order processing system with Laravel Horizon, Redis queues, RabbitMQ support, KPI generation, and customer leaderboards.

## System Requirements
- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **Redis**: 6.0+ (required for queues and caching)
- **RabbitMQ**: 3.8+ (optional, for distributed queuing)
- **Database**: SQLite (default), MySQL 8.0+, or PostgreSQL 13+
- **Supervisor**: For production queue worker management

## Quick Start

### 1. Start Redis
```bash
# macOS with Homebrew
brew services start redis

# Linux (Ubuntu/Debian)
sudo systemctl start redis

# Docker
docker run -d -p 6379:6379 --name redis redis:alpine
```

### 2. Run Setup Script
```bash
./start.sh
```

### 3. Start Services

**Terminal 1 - Horizon (Queue Worker):**
```bash
php artisan horizon
```

**Terminal 2 - Web Server:**
```bash
php artisan serve
```

**Terminal 3 - Scheduler (for KPIs):**
```bash
php artisan schedule:work
```

### 4. Import Orders
```bash
php artisan orders:import sample.csv
```

## Project Structure

```
app/
├── Console/Commands/
│   └── ImportOrders.php          # CSV import command
├── Http/Controllers/Api/
│   ├── KpiController.php         # KPI API endpoints
│   ├── LeaderboardController.php # Leaderboard API endpoints
│   └── SystemController.php      # Health check endpoints
├── Jobs/
│   ├── ProcessOrderWorkflow.php  # Main workflow orchestrator
│   ├── ReserveStock.php          # Stock reservation
│   ├── ProcessPayment.php        # Payment processing
│   ├── FinalizeOrder.php         # Order completion
│   └── RollbackOrder.php         # Failure handling
├── Models/
│   ├── Order.php                 # Order model
│   ├── Product.php               # Product model
│   ├── StockReservation.php      # Stock reservation model
│   └── Payment.php               # Payment model
├── Observers/
│   └── OrderObserver.php         # Automatic leaderboard updates
└── Services/
    ├── KpiService.php            # KPI calculations
    └── LeaderboardService.php    # Customer ranking
```

## Features Implemented

### 1. CSV Import with Queued Processing
- **Command**: `php artisan orders:import file.csv`
- Parses large CSV files
- Creates order records
- Dispatches jobs to queue for async processing
- Progress bar for visual feedback

### 2. Order Workflow
The system implements a multi-step workflow:

```
Order Created → Reserve Stock → Process Payment → Finalize
                      ↓                ↓
                   Failed         Rollback
```

**Jobs Chain:**
1. **ReserveStock**: Checks inventory, decrements stock, creates reservation
2. **ProcessPayment**: Simulates payment gateway (90% success rate)
3. **FinalizeOrder**: Marks order complete, commits stock reservation
4. **RollbackOrder**: On failure, releases stock, marks order failed

### 3. KPI Generation (Redis-Cached)
- Daily revenue, order count, average order value
- Real-time metrics (updated every 5 minutes)
- Date range queries
- Automatic caching with TTL

### 4. Customer Leaderboard (Redis Sorted Sets)
- Top customers by total spending
- Customer rank lookup
- Product sales leaderboard
- Auto-updates when orders complete
- Redis sorted sets for performance

### 5. Laravel Horizon Dashboard
- Real-time queue monitoring
- Job metrics and throughput
- Failed job management
- Worker auto-scaling
- Dashboard at `/horizon`

### 6. Supervisor Integration
- Production-ready worker management
- Auto-restart on failure
- Graceful shutdowns
- Configuration included

## API Documentation

### Health Check
```bash
GET /api/health
```
Returns system status (database, Redis, queue).

### System Stats
```bash
GET /api/stats
```
Returns order statistics and today's metrics.

### Daily KPIs
```bash
GET /api/kpis/daily
GET /api/kpis/daily?date=2025-11-10
```

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2025-11-10",
    "total_revenue": 1234.56,
    "order_count": 25,
    "average_order_value": 49.38,
    "unique_customers": 18,
    "generated_at": "2025-11-15T12:00:00Z"
  }
}
```

### KPI Range
```bash
GET /api/kpis/range?start_date=2025-11-10&end_date=2025-11-15
```

### Real-time KPIs
```bash
GET /api/kpis/realtime
```

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_orders": 5,
    "processing_orders": 12,
    "completed_today": 45,
    "failed_today": 2,
    "revenue_today": 2345.67
  }
}
```

### Customer Leaderboard
```bash
GET /api/leaderboard/customers?limit=10
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "rank": 1,
      "customer_id": 501,
      "customer_name": "John Doe",
      "order_count": 15,
      "total_spent": 2345.67
    }
  ]
}
```

### Customer Rank
```bash
GET /api/leaderboard/customers/501/rank
```

### Product Leaderboard
```bash
GET /api/leaderboard/products?limit=10
```

### Rebuild Leaderboard
```bash
POST /api/leaderboard/rebuild
```

## Configuration

### Queue Configuration (`config/queue.php`)
- **Redis**: Default queue driver
- **RabbitMQ**: Optional alternative (configured)
- Connections support both Redis and RabbitMQ

### Horizon Configuration (`config/horizon.php`)
- **supervisor-orders**: Dedicated for order processing (5-20 workers)
- **supervisor-default**: For general jobs (3-10 workers)
- Auto-scaling based on queue load
- Metrics retention: 24 hours

### Environment Variables (`.env`)
```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=predis
REDIS_HOST=localhost
REDIS_PORT=6379

RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

## Production Deployment

### 1. Supervisor Setup
```bash
# Copy configuration
sudo cp horizon.conf /etc/supervisor/conf.d/order-processing-horizon.conf

# Edit and update paths
sudo nano /etc/supervisor/conf.d/order-processing-horizon.conf

# Update Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start order-processing-horizon:*
```

### 2. Enable Scheduler
Add to crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Optimize Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 4. Monitor Logs
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Horizon logs
tail -f storage/logs/horizon.log

# Supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log
```

## Monitoring & Troubleshooting

### Check Queue Status
```bash
# View Horizon dashboard
http://localhost:8000/horizon

# Command line
php artisan queue:failed
php artisan horizon:list
```

### Retry Failed Jobs
```bash
# Retry all
php artisan queue:retry all

# Retry specific job
php artisan queue:retry <job-id>
```

### Clear Queue
```bash
php artisan horizon:clear
php artisan horizon:clear --queue=orders
```

### Redis Monitoring
```bash
redis-cli
> INFO stats
> KEYS *kpis*
> KEYS *leaderboard*
> LLEN queues:orders
> ZRANGE leaderboard:customers:spending 0 9 WITHSCORES
```

### Database Queries
```bash
php artisan tinker
```

```php
// Check order statistics
\App\Models\Order::count();
\App\Models\Order::where('status', 'completed')->count();

// Check latest orders
\App\Models\Order::latest()->take(10)->get();

// Check product inventory
\App\Models\Product::all();

// Check failed orders
\App\Models\Order::whereIn('status', ['failed', 'rollback'])->get();
```

## Performance Optimization

### Queue Workers
- Orders queue: Up to 20 workers in production
- Auto-scaling based on load
- Memory limit: 128MB per worker
- Job timeout: 300 seconds

### Caching Strategy
- **Daily KPIs**: 24-hour TTL
- **Range KPIs**: 1-hour TTL
- **Leaderboard**: 1-hour TTL
- **Real-time metrics**: 5-minute TTL

### Database Indexes
- Orders: `customer_id`, `status`, `order_date`
- Products: `sku`
- Stock Reservations: `product_sku`, `status`
- Payments: `order_id`, `status`

## Testing

### Test Order Import
```bash
php artisan orders:import sample.csv
```

### Test API Endpoints
```bash
# Health check
curl http://localhost:8000/api/health

# System stats
curl http://localhost:8000/api/stats

# KPIs
curl http://localhost:8000/api/kpis/daily
curl http://localhost:8000/api/kpis/realtime

# Leaderboard
curl http://localhost:8000/api/leaderboard/customers
curl http://localhost:8000/api/leaderboard/products
```

### Load Testing
```bash
# Create sample CSV with 1000 orders
# Then import and monitor
php artisan orders:import large-sample.csv

# Monitor in Horizon dashboard
# Watch: throughput, wait times, memory usage
```

## Scaling Considerations

### Horizontal Scaling
- Run multiple Horizon instances
- Use Redis Cluster for distributed caching
- Deploy RabbitMQ cluster for queue distribution

### Vertical Scaling
- Increase worker count in `config/horizon.php`
- Allocate more memory to workers
- Optimize database queries with eager loading

### Monitoring
- Set up log aggregation (ELK, Datadog)
- Monitor Redis memory usage
- Track queue depth and wait times
- Alert on failed job thresholds

## Security Notes

- Horizon dashboard requires authentication in production
- Configure `HorizonServiceProvider` for access control
- Use HTTPS in production
- Secure Redis with password
- Limit RabbitMQ access

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Horizon dashboard: `/horizon`
3. Review Redis keys for data integrity
4. Verify all services are running (Redis, database, Horizon)

## License
Technical assessment project for Next Ventures.


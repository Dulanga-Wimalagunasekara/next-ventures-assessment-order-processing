# Order Processing System - Setup & Usage Guide

## System Overview
This Laravel application processes large CSV order files using queued jobs with:
- Redis queue management with Laravel Horizon
- RabbitMQ support for distributed queuing
- Order workflow: Reserve Stock → Process Payment → Finalize or Rollback
- Daily KPI generation (revenue, order count, average order value)
- Customer leaderboard using Redis sorted sets
- Real-time metrics tracking

## Prerequisites
- PHP 8.2+
- Composer
- Redis server running on localhost:6379
- RabbitMQ server running on localhost:5672 (optional)
- SQLite database (default) or MySQL/PostgreSQL

## Installation

### 1. Install Dependencies
```bash
composer install
```

### 2. Configure Environment
The `.env` file is already configured with:
- Queue connection: Redis
- Cache driver: Redis
- Redis: localhost:6379
- RabbitMQ: localhost:5672 (optional)

### 3. Run Migrations
```bash
php artisan migrate
```

### 4. Seed Products
```bash
php artisan db:seed --class=ProductSeeder
```

## Running the Application

### 1. Start Laravel Horizon
Horizon manages all queue workers and provides a beautiful dashboard.

```bash
php artisan horizon
```

Access the dashboard at: `http://localhost:8000/horizon`

### 2. Start the Development Server
In a new terminal:
```bash
php artisan serve
```

### 3. Import Orders from CSV
```bash
php artisan orders:import sample.csv
```

This command will:
- Parse the CSV file
- Create order records in the database
- Dispatch each order to the queue for processing

### 4. Monitor Progress
- **Horizon Dashboard**: http://localhost:8000/horizon
- **View Metrics**: Jobs processed, failed jobs, throughput
- **Queue Status**: Real-time queue depth and wait times

## API Endpoints

### KPI Endpoints

#### Get Daily KPIs
```bash
curl http://localhost:8000/api/kpis/daily
curl http://localhost:8000/api/kpis/daily?date=2025-11-10
```

Response:
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

#### Get KPIs for Date Range
```bash
curl "http://localhost:8000/api/kpis/range?start_date=2025-11-10&end_date=2025-11-15"
```

#### Get Real-time KPIs
```bash
curl http://localhost:8000/api/kpis/realtime
```

Response:
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

### Leaderboard Endpoints

#### Get Top Customers
```bash
curl http://localhost:8000/api/leaderboard/customers
curl http://localhost:8000/api/leaderboard/customers?limit=20
```

Response:
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

#### Get Customer Rank
```bash
curl http://localhost:8000/api/leaderboard/customers/501/rank
```

#### Get Top Products
```bash
curl http://localhost:8000/api/leaderboard/products?limit=10
```

#### Rebuild Leaderboard
```bash
curl -X POST http://localhost:8000/api/leaderboard/rebuild
```

## Order Processing Workflow

### Workflow Steps:
1. **Reserve Stock**: Check inventory and reserve items
2. **Process Payment**: Simulate payment processing (90% success rate)
3. **Finalize Order**: Mark order as completed and commit stock
4. **Rollback** (on failure): Release reserved stock, mark order as failed

### Order Status Flow:
```
pending → reserved → payment_processing → completed
                ↓                    ↓
              failed          →   rollback
```

### Example Workflow Execution:
```bash
# Import orders
php artisan orders:import sample.csv

# Watch Horizon dashboard for processing
# Orders will automatically progress through the workflow
```

## Scheduled Tasks

The application includes scheduled tasks (configured in `routes/console.php`):

### Run Scheduler (for production)
```bash
php artisan schedule:work
```

Scheduled tasks:
- **Hourly**: Generate daily KPIs and update real-time metrics
- **Every 30 minutes**: Rebuild customer leaderboard
- **Every 5 minutes**: Take Horizon snapshots for metrics

## Production Deployment with Supervisor

### 1. Copy Supervisor Configuration
```bash
sudo cp horizon.conf /etc/supervisor/conf.d/order-processing-horizon.conf
```

### 2. Update Paths in Configuration
Edit `/etc/supervisor/conf.d/order-processing-horizon.conf`:
- Replace `/path/to/your/project` with actual path
- Set correct user (e.g., `www-data`)

### 3. Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start order-processing-horizon:*
```

### 4. Monitor Supervisor
```bash
sudo supervisorctl status
```

## Testing Order Processing

### 1. Check Database
```bash
php artisan tinker
```

```php
// Check orders
\App\Models\Order::count();
\App\Models\Order::where('status', 'completed')->count();

// Check products
\App\Models\Product::all();

// Check stock reservations
\App\Models\StockReservation::where('status', 'committed')->count();
```

### 2. Monitor Redis
```bash
redis-cli
> KEYS *kpis*
> KEYS *leaderboard*
> GET kpis:daily:2025-11-15
> ZRANGE leaderboard:customers:spending 0 -1 WITHSCORES
```

### 3. Check Queue Status
```bash
# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear specific queue
php artisan horizon:clear --queue=orders
```

## Troubleshooting

### Issue: Jobs not processing
**Solution**: Ensure Horizon is running
```bash
php artisan horizon
```

### Issue: Redis connection failed
**Solution**: Check Redis is running
```bash
redis-cli ping
# Should return: PONG
```

### Issue: Orders stuck in pending
**Solution**: Check Horizon logs
```bash
tail -f storage/logs/laravel.log
```

### Issue: Stock not reserved
**Solution**: Check product inventory
```bash
php artisan tinker
\App\Models\Product::where('sku', 'SKU-001')->first();
```

## Architecture Overview

### Components:
- **ImportOrders Command**: Parses CSV and queues orders
- **ProcessOrderWorkflow Job**: Orchestrates the workflow chain
- **ReserveStock Job**: Manages inventory reservations
- **ProcessPayment Job**: Simulates payment processing
- **FinalizeOrder Job**: Completes successful orders
- **RollbackOrder Job**: Handles failures and releases stock
- **KpiService**: Generates and caches KPI metrics
- **LeaderboardService**: Manages customer rankings in Redis
- **OrderObserver**: Updates metrics when orders change

### Database Schema:
- **orders**: Main order records
- **products**: Product inventory
- **stock_reservations**: Temporary stock holds
- **payments**: Payment transaction records

### Redis Usage:
- Queue jobs (Redis lists)
- KPI caching (Redis strings with TTL)
- Customer leaderboard (Redis sorted sets)
- Real-time metrics (Redis strings)

## Performance Optimization

### Queue Workers:
- Orders queue: 5 workers (configurable in horizon.php)
- Auto-scaling based on queue load
- Maximum 20 workers in production

### Caching:
- Daily KPIs: 24-hour cache
- Range KPIs: 1-hour cache
- Leaderboard: 1-hour cache
- Real-time metrics: 5-minute cache

### Database Indexes:
- Orders: customer_id, status, order_date
- Products: sku
- Stock Reservations: product_sku, status
- Payments: order_id, status

## License
This is a technical assessment project.


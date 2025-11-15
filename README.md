# Laravel Order Processing System

A production-ready Laravel application for processing large-scale order CSV imports with asynchronous queue processing, Redis caching, and real-time analytics.

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Redis (localhost:6379)
- RabbitMQ (localhost:5672) - Optional

### Installation

1. **Start Redis**
```bash
# macOS
brew services start redis

# Linux
sudo systemctl start redis

# Docker
docker run -d -p 6379:6379 --name redis redis:alpine
```

2. **Run Setup**
```bash
./start.sh
```

3. **Start Services**

**Terminal 1 - Queue Worker:**
```bash
php artisan horizon
```

**Terminal 2 - Web Server:**
```bash
php artisan serve
```

**Terminal 3 - Scheduler:**
```bash
php artisan schedule:work
```

4. **Import Orders**
```bash
php artisan orders:import sample.csv
```

5. **View Dashboard**
- Horizon: http://localhost:8000/horizon
- Health Check: http://localhost:8000/api/health

## 📋 Features

### ✅ CSV Order Import
- Command: `php artisan orders:import file.csv`
- Queued processing for scalability
- Progress tracking
- Error handling

### ✅ Order Workflow
```
pending → reserve stock → process payment → finalize
              ↓                  ↓
           failed         →  rollback
```

**Workflow Steps:**
1. **Reserve Stock** - Check inventory, reserve items
2. **Process Payment** - Simulate payment gateway (90% success)
3. **Finalize Order** - Complete order, commit stock
4. **Rollback** - On failure, release stock

### ✅ KPI Generation (Redis Cached)
- Daily revenue, order count, average order value
- Real-time metrics
- Date range queries
- Customer analytics

### ✅ Customer Leaderboard (Redis Sorted Sets)
- Top customers by spending
- Product bestsellers
- Auto-updates on order completion
- Fast rank lookups

### ✅ Order Notification System
- Email and log notifications for order status
- Queued notification jobs (non-blocking)
- Notification history tracking
- Success/failure notifications
- Resend failed notifications via API
- Customer notification preferences

### ✅ Laravel Horizon
- Real-time queue monitoring
- Job metrics and throughput
- Failed job management
- Auto-scaling workers
- Dashboard at `/horizon`

### ✅ Supervisor Ready
- Production configuration included
- Auto-restart on failure
- Graceful shutdowns
- Log rotation

## 🔌 API Endpoints

### Health & Stats
```bash
GET /api/health         # System status
GET /api/stats          # Order statistics
```

### KPIs
```bash
GET /api/kpis/daily                               # Today's KPIs
GET /api/kpis/daily?date=2025-11-10               # Specific date
GET /api/kpis/range?start_date=X&end_date=Y       # Date range
GET /api/kpis/realtime                            # Real-time metrics
```

### Leaderboard
```bash
GET  /api/leaderboard/customers                   # Top customers
GET  /api/leaderboard/customers/{id}/rank         # Customer rank
GET  /api/leaderboard/products                    # Top products
POST /api/leaderboard/rebuild                     # Rebuild leaderboard
```

### Notifications
```bash
GET  /api/notifications                           # All notifications (with filters)
GET  /api/notifications/order/{orderId}          # Notifications for specific order
GET  /api/notifications/stats                    # Notification statistics
GET  /api/notifications/recent                   # Recent notifications
POST /api/notifications/{id}/resend              # Resend failed notification
```

**Notification Filters:**
```bash
GET /api/notifications?type=success              # Filter by type (success/failed)
GET /api/notifications?channel=email             # Filter by channel (email/log)
GET /api/notifications?status=sent               # Filter by status (pending/sent/failed)
GET /api/notifications?customer_id=501           # Filter by customer
GET /api/notifications?from_date=2025-11-10      # Date range filtering
```

## 📊 Example Response

**Daily KPIs:**
```json
{
  "success": true,
  "data": {
    "date": "2025-11-15",
    "total_revenue": 1234.56,
    "order_count": 25,
    "average_order_value": 49.38,
    "unique_customers": 18
  }
}
```

**Customer Leaderboard:**
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

**Notification Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_reference": "1001",
      "notification_type": "success",
      "channel": "log",
      "customer_id": 501,
      "order_status": "completed",
      "total_amount": 51.00,
      "message": "Order 1001 has been processed successfully...",
      "status": "sent",
      "sent_at": "2025-11-15T12:30:00.000000Z",
      "created_at": "2025-11-15T12:30:00.000000Z"
    }
  ]
}
```

**Notification Stats:**
```json
{
  "success": true,
  "data": {
    "total_notifications": 45,
    "success_notifications": 38,
    "failed_notifications": 7,
    "sent_notifications": 42,
    "pending_notifications": 2,
    "failed_sends": 1,
    "by_channel": {
      "email": 5,
      "log": 40
    },
    "today": {
      "total": 12,
      "success": 10,
      "failed": 2
    }
  }
}
```

## 🧪 Testing

Run the test suite:
```bash
./test.sh
```

Manual testing:
```bash
# Import sample orders
php artisan orders:import sample.csv

# Check health
curl http://localhost:8000/api/health

# View KPIs
curl http://localhost:8000/api/kpis/daily

# View leaderboard
curl http://localhost:8000/api/leaderboard/customers

# View notifications
curl http://localhost:8000/api/notifications

# View notification stats
curl http://localhost:8000/api/notifications/stats

# View notifications for specific order
curl http://localhost:8000/api/notifications/order/1001
```

## 📁 Project Structure

```
app/
├── Console/Commands/
│   └── ImportOrders.php              # CSV import command
├── Http/Controllers/Api/
│   ├── KpiController.php             # KPI endpoints
│   ├── LeaderboardController.php     # Leaderboard endpoints
│   ├── NotificationController.php    # Notification endpoints
│   └── SystemController.php          # Health check
├── Jobs/
│   ├── ProcessOrderWorkflow.php      # Workflow orchestrator
│   ├── ReserveStock.php              # Stock reservation
│   ├── ProcessPayment.php            # Payment processing
│   ├── FinalizeOrder.php             # Order completion
│   ├── RollbackOrder.php             # Failure handling
│   └── SendOrderNotification.php     # Notification delivery
├── Models/
│   ├── Order.php
│   ├── Product.php
│   ├── StockReservation.php
│   ├── Payment.php
│   └── Notification.php             # Notification history
├── Observers/
│   └── OrderObserver.php             # Auto-update leaderboard
└── Services/
    ├── KpiService.php                # KPI calculations
    ├── LeaderboardService.php        # Customer ranking
    └── NotificationService.php       # Notification management
```

## 🗄️ Database Schema

- **orders** - Order records with status tracking
- **products** - Product inventory
- **stock_reservations** - Temporary stock holds
- **payments** - Payment transactions
- **notifications** - Notification history and status

## ⚙️ Configuration

### Queue (Redis)
```env
QUEUE_CONNECTION=redis
REDIS_HOST=localhost
REDIS_PORT=6379
```

### Cache (Redis)
```env
CACHE_STORE=redis
```

### RabbitMQ (Optional)
```env
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
```

### Horizon
- Configuration: `config/horizon.php`
- Dashboard: `/horizon`
- Workers: Auto-scaling (5-20 for orders queue)

## 📦 Production Deployment

### 1. Supervisor Setup
```bash
sudo cp horizon.conf /etc/supervisor/conf.d/order-processing-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start order-processing-horizon:*
```

### 2. Cron Job (Scheduler)
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

## 📚 Documentation

- **SETUP.md** - Complete setup and usage guide
- **DEPLOYMENT.md** - Production deployment guide
- **PROJECT_SUMMARY.md** - Technical implementation details
- **horizon.conf** - Supervisor configuration
- **start.sh** - Automated setup script
- **test.sh** - Testing script

## 🔍 Monitoring

### Horizon Dashboard
```
http://localhost:8000/horizon
```

### Queue Commands
```bash
php artisan horizon:list          # List supervisors
php artisan queue:failed          # View failed jobs
php artisan queue:retry all       # Retry failed jobs
php artisan horizon:clear         # Clear queue
```

### Redis Monitoring
```bash
redis-cli
> INFO stats
> KEYS *kpis*
> KEYS *leaderboard*
> LLEN queues:orders
```

## 🛠️ Troubleshooting

### Redis Connection Error
```bash
# Check if Redis is running
redis-cli ping

# Start Redis
brew services start redis  # macOS
sudo systemctl start redis # Linux
```

### Jobs Not Processing
```bash
# Ensure Horizon is running
php artisan horizon

# Check logs
tail -f storage/logs/laravel.log
```

### View Failed Jobs
```bash
php artisan queue:failed
php artisan queue:retry all
```

## 📊 Performance

- **Queue Workers**: Auto-scaling (5-20 workers)
- **Job Timeout**: 300 seconds
- **Cache TTL**: 1-24 hours
- **Leaderboard**: O(log n) Redis sorted sets
- **Database**: Indexed for performance

## 🔒 Security

- Horizon authentication (configured in `HorizonServiceProvider`)
- Input validation on all endpoints
- Database transactions for consistency
- Error message sanitization

## 📄 License

Technical assessment project for Next Ventures.

---

## 💡 Tips

1. **Monitor Horizon Dashboard** - Real-time queue insights
2. **Check Logs** - All workflow steps are logged
3. **Use Redis Caching** - Improved API performance
4. **Scale Workers** - Adjust in `config/horizon.php`
5. **Test Locally First** - Use `./test.sh` before production

## 🤝 Support

For issues:
1. Check `storage/logs/laravel.log`
2. View Horizon dashboard at `/horizon`
3. Verify Redis connection: `redis-cli ping`
4. Review configuration in `.env`

---

**Built with Laravel 12 + Horizon + Redis + RabbitMQ**


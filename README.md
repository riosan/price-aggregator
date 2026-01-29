# 🚀 High-Performance Price Aggregator (2026)

A professional-grade price monitoring engine built with **Laravel 12** and **Swoole/Octane**, designed for extreme speed, scalability, and automated insights.

## 🏗 Technical Architecture & Highlights

*   **Persistent Memory Execution (Swoole/Octane):** The application runs in a persistent state, eliminating framework bootstrapping overhead. This results in sub-millisecond response times and high-concurrency handling.
*   **Asynchronous Scraping Engine:** Implemented a multi-threaded parser using **Guzzle HTTP Pooling**. By executing requests concurrently, the system reduces the crawling window by 10x compared to traditional sequential scrapers.
*   **Data-Driven Logic:** All CSS selectors and scraping parameters are stored as **JSON configurations** in the database. This allows for real-time updates and adding new retailers without code deployment or downtime.
*   **Event-Driven Notifications:** Integrated **Telegram Bot API** for real-time alerts. The system uses "Smart Alert" logic, notifying users only upon significant price fluctuations.
*   **Time-Series Data Tracking:** Maintains a comprehensive price history for every product, enabling rich time-series visualizations via **Chart.js**.

## 🛠 Tech Stack
- **Backend:** PHP 8.4, Laravel 12 (Octane, Swoole)
- **Database:** MySQL 8.0, **Redis** (for caching and queue management)
- **Frontend:** Tailwind CSS, **Chart.js**
- **Infrastructure:** Docker & Docker Compose
- **Code Quality:** PSR-12 compliant, enforced via **Laravel Pint**

## 📦 Installation & Setup Guide

Follow these steps to get the project running locally in a Docker environment.

### 1. Clone the repository
```bash
git clone https://github.com
cd price-aggregator

 ### 2. Configure Environment
Copy the example environment file and fill in your Telegram Bot Token and Chat ID:
bash
cp .env.example .env

### 3. Spin Up Containers
Build and start the application using Docker Compose:
bash
docker-compose up -d --build

### 4. Initialize Application
Install dependencies, generate app key, and seed the database with real GPU tracking data:
bash
docker-compose exec aggregator-app php artisan key:generate
docker-compose exec aggregator-app php artisan migrate --seed

### 5. Run the Parser
Manually trigger the first price collection:
bash
docker-compose exec aggregator-app php artisan parse:prices

### 6. Enable Automation (Optional)
To run the parser automatically every 30 minutes, start the scheduler worker:
bash
docker-compose exec aggregator-app php artisan schedule:work

📈 Usage & Monitoring
Web Interface: Access the dashboard at http://localhost (or your configured domain) to see price charts.
Telegram Alerts: You will receive instant notifications whenever a price drop or increase is detected.
Logs: Monitor system activity with docker-compose logs -f aggregator-app.

git clone https://github.com/aki961996/ecome.git
cd ecome

composer install

cp .env.example .env
php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

php artisan migrate

php artisan demo:setup-order

php artisan queue:table
php artisan migrate


php artisan queue:work --queue=default --tries=3 --timeout=120

POST http://localhost:8000/api/orders/1/process

GET http://localhost:8000/api/dashboard/orders-summary
php artisan queue:work
php artisan queue:restart

Logs
storage/logs/laravel.log

php artisan demo:setup-orderphp artisan demo:setup-order

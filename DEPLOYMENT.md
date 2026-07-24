# cPanel Deployment Guide

## 1. Upload the project
Upload the contents of this folder to your cPanel hosting account.

## 2. Set the document root
Point your domain to the public folder of the Laravel project.

## 3. Configure environment
Update .env with your production values:
- APP_ENV=production
- APP_DEBUG=false
- APP_URL=https://your-domain.com
- DB_CONNECTION=mysql
- DB_HOST=localhost
- DB_PORT=3306
- DB_DATABASE=your_database
- DB_USERNAME=your_user
- DB_PASSWORD=your_password

## 4. Run these commands on the server
```bash
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

## 5. Verify
Open your domain in the browser and log in.

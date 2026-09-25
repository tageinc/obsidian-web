docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml up -d --build app

docker compose --env-file .env.docker up -d --build app

docker compose --env-file .env.docker exec -T -e APP_ENV=local app php artisan db:seed --class=DeviceSeederRS --force

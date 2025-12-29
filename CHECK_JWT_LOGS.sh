#!/bin/bash
# Check JWT authentication logs
docker compose -f docker-compose.prod.yml exec app tail -100 storage/logs/laravel.log | grep -A 10 -B 5 -i "jwt\|token\|bearer\|test token\|User found\|Unauthenticated"



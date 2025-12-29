#!/bin/bash
# Check nginx error logs
docker compose -f docker-compose.prod.yml exec app tail -50 /var/log/nginx.err.log



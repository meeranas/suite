#!/bin/bash
# Get nginx error details
docker compose -f docker-compose.prod.yml exec app sh -c "
    echo '=== Nginx Error Log ==='
    tail -50 /var/log/nginx/error.log 2>/dev/null || echo 'No error.log found'
    echo ''
    echo '=== Nginx Error Log (err.log) ==='
    tail -50 /var/log/nginx.err.log 2>/dev/null || echo 'No err.log found'
    echo ''
    echo '=== Supervisor Logs ==='
    tail -50 /var/log/nginx.err.log 2>/dev/null || echo 'No supervisor logs'
"



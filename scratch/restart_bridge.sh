#!/bin/bash
pkill -9 -f "node server-twilio.js" || true
sleep 1
cd /var/www/html && nohup node server-twilio.js > /var/log/node-bridge.log 2>&1 &
sleep 1
ps aux | grep "node server-twilio"

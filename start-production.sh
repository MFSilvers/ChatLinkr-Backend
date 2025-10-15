#!/bin/bash

# ChatLinkr Production Startup Script

echo "🚀 Starting ChatLinkr Production Server..."

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo "Please copy env.example to .env and configure your settings."
    exit 1
fi

# Check if composer dependencies are installed
if [ ! -d "vendor" ]; then
    echo "📦 Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Check if Node.js dependencies are installed
if [ ! -d "websocket/node_modules" ]; then
    echo "📦 Installing Node.js dependencies..."
    cd websocket && npm install --production && cd ..
fi

# Create logs directory
mkdir -p logs

# Set permissions
chmod 755 .
chmod 644 .htaccess
chmod 644 api/*.php
chmod 644 config/*.php
chmod 644 utils/*.php

echo "✅ Backend ready for production!"
echo ""
echo "📋 Next steps:"
echo "1. Configure your web server to point to this directory"
echo "2. Set up your database and run the schema"
echo "3. Start the WebSocket server: cd websocket && npm start"
echo "4. Configure your frontend to point to this API"
echo ""
echo "🌐 API will be available at: http://yourdomain.com/api/"
echo "🔌 WebSocket server: npm start (or use PM2 for production)"

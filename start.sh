#!/bin/bash

# Script per avviare sia il backend PHP che il WebSocket server

echo "🚀 Avvio ChatLinkr Backend + WebSocket Server..."

# Le dipendenze Node.js sono già installate da Railway
echo "📦 Dipendenze Node.js già installate da Railway"

# Avvia il WebSocket server in background
echo "📡 Avvio WebSocket server sulla porta ${WEBSOCKET_PORT:-3001}..."
cd websocket
node server.js &
WEBSOCKET_PID=$!

# Torna alla directory backend
cd ..

# Avvia il server PHP
echo "🌐 Avvio server PHP sulla porta ${PORT:-8000}..."
php -S 0.0.0.0:${PORT:-8000} -t . index.php &
PHP_PID=$!

echo "✅ Servizi avviati:"
echo "   - PHP Backend: http://localhost:${PORT:-8000}"
echo "   - WebSocket: http://localhost:${WEBSOCKET_PORT:-3001}"
echo ""

# Funzione per cleanup
cleanup() {
    echo ""
    echo "🛑 Fermando servizi..."
    kill $WEBSOCKET_PID 2>/dev/null
    kill $PHP_PID 2>/dev/null
    echo "✅ Servizi fermati"
    exit 0
}

# Cattura segnali di terminazione
trap cleanup SIGINT SIGTERM

# Mantieni lo script attivo
wait

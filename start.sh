#!/bin/bash

# Script per avviare sia il backend PHP che il WebSocket server

echo "🚀 Avvio ChatLinkr Backend + WebSocket Server..."

# Le dipendenze Node.js sono già installate da Railway
echo "📦 Dipendenze Node.js già installate da Railway"

# Avvia il server PHP sulla porta principale
echo "🌐 Avvio server PHP sulla porta ${PORT:-8080}..."
php -S 0.0.0.0:${PORT:-8080} -t . index.php &
PHP_PID=$!

# Avvia il WebSocket server su una porta diversa (ma non esposta)
echo "📡 Avvio WebSocket server sulla porta 3001 (interno)..."
cd websocket
node server.js &
WEBSOCKET_PID=$!

echo "✅ Servizi avviati:"
echo "   - PHP Backend: http://localhost:${PORT:-8080}"
echo "   - WebSocket: porta 3001 (interno)"
echo ""

# Funzione per cleanup
cleanup() {
    echo ""
    echo "🛑 Fermando servizi..."
    kill $PHP_PID 2>/dev/null
    kill $WEBSOCKET_PID 2>/dev/null
    echo "✅ Servizi fermati"
    exit 0
}

# Cattura segnali di terminazione
trap cleanup SIGINT SIGTERM

# Mantieni lo script attivo
wait

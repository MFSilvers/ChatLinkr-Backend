#!/bin/bash

# Script per avviare sia il backend PHP che il WebSocket server

echo "🚀 Avvio ChatLinkr Backend + WebSocket Server..."

# Le dipendenze Node.js sono già installate da Railway
echo "📦 Dipendenze Node.js già installate da Railway"

# Avvia il server PHP su porta interna
echo "🌐 Avvio server PHP sulla porta 8081 (interno)..."
php -S 0.0.0.0:8081 -t . index.php &
PHP_PID=$!

# Aspetta che PHP sia pronto
echo "⏳ Attendo che PHP sia pronto..."
sleep 3

# Avvia il WebSocket server sulla porta principale (include proxy per API PHP)
echo "📡 Avvio WebSocket server sulla porta ${PORT:-8080}..."
cd "$(dirname "$0")/websocket" || exit 1

# Verifica che server.js esista
if [ ! -f "server.js" ]; then
    echo "❌ Errore: server.js non trovato nella cartella websocket"
    exit 1
fi

node server.js &
WEBSOCKET_PID=$!

echo "✅ Servizi avviati:"
echo "   - WebSocket + API Proxy: http://localhost:${PORT:-8080}"
echo "   - PHP Backend (interno): http://localhost:8081"
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

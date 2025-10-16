#!/bin/bash

# Script per avviare sia il backend PHP che il WebSocket server

echo "🚀 Avvio ChatLinkr Backend + WebSocket Server..."

# Le dipendenze Node.js sono già installate da Railway
echo "📦 Dipendenze Node.js già installate da Railway"

# Avvia il WebSocket server (che include anche le API HTTP)
echo "📡 Avvio WebSocket server sulla porta ${PORT:-8080}..."
cd websocket
node server.js &
SERVER_PID=$!

echo "✅ Servizio avviato:"
echo "   - WebSocket + API: http://localhost:${PORT:-8080}"
echo ""

# Funzione per cleanup
cleanup() {
    echo ""
    echo "🛑 Fermando servizio..."
    kill $SERVER_PID 2>/dev/null
    echo "✅ Servizio fermato"
    exit 0
}

# Cattura segnali di terminazione
trap cleanup SIGINT SIGTERM

# Mantieni lo script attivo
wait

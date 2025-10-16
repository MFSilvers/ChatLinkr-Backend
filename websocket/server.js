const http = require('http');
const { Server } = require('socket.io');
const jwt = require('jsonwebtoken');
const { Client } = require('pg');
require('dotenv').config();

const PORT = process.env.PORT || 8080;
const SECRET_KEY = process.env.SESSION_SECRET;

// Validate required environment variables
if (!SECRET_KEY) {
  console.error('❌ Error: SESSION_SECRET environment variable is required');
  process.exit(1);
}

const server = http.createServer((req, res) => {
  // Set CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Content-Type', 'application/json');

  // Handle preflight OPTIONS requests
  if (req.method === 'OPTIONS') {
    res.writeHead(200);
    res.end();
    return;
  }

  // Health check endpoint
  if (req.url === '/' && req.method === 'GET') {
    res.writeHead(200);
    res.end(JSON.stringify({
      name: 'ChatLinkr API',
      version: '1.0.0',
      status: 'running',
      websocket: 'active',
      endpoints: {
        'GET /': 'Health check',
        'WebSocket': 'Real-time messaging',
        'API': 'PHP backend via proxy'
      }
    }));
    return;
  }

  // Proxy API requests to PHP backend
  if (req.url.startsWith('/api/')) {
    const phpPort = 8081; // PHP backend on internal port
    
    console.log(`🔄 Proxying ${req.method} ${req.url} to PHP backend`);
    
    // Copy headers but remove problematic ones
    const headers = { ...req.headers };
    delete headers.host; // Remove host header to avoid conflicts
    
    const options = {
      hostname: 'localhost',
      port: phpPort,
      path: req.url,
      method: req.method,
      headers: headers,
      timeout: 10000 // 10 second timeout
    };

    const proxyReq = http.request(options, (proxyRes) => {
      console.log(`✅ Proxy response: ${proxyRes.statusCode} for ${req.method} ${req.url}`);
      res.writeHead(proxyRes.statusCode, proxyRes.headers);
      proxyRes.pipe(res);
    });

    proxyReq.on('error', (err) => {
      console.error(`❌ Proxy error for ${req.method} ${req.url}:`, err.message);
      res.writeHead(500);
      res.end(JSON.stringify({ error: 'Internal server error', details: err.message }));
    });

    proxyReq.on('timeout', () => {
      console.error(`⏰ Proxy timeout for ${req.method} ${req.url}`);
      proxyReq.destroy();
      res.writeHead(504);
      res.end(JSON.stringify({ error: 'Gateway timeout' }));
    });

    // Handle request body for POST/PUT requests
    if (req.method === 'POST' || req.method === 'PUT' || req.method === 'PATCH') {
      let body = '';
      req.on('data', (chunk) => {
        body += chunk.toString();
      });
      req.on('end', () => {
        proxyReq.write(body);
        proxyReq.end();
      });
    } else {
      // For GET, DELETE, etc., pipe directly
      req.pipe(proxyReq);
    }
    return;
  }

  // For other endpoints, return 404
  res.writeHead(404);
  res.end(JSON.stringify({ error: 'Endpoint not found' }));
});

const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

const users = new Map();

async function updateUserStatus(userId, isOnline) {
  const client = new Client({
    connectionString: process.env.DATABASE_URL
  });
  
  try {
    await client.connect();
    await client.query(
      'UPDATE users SET is_online = $1, last_seen = CURRENT_TIMESTAMP WHERE id = $2',
      [isOnline, userId]
    );
  } catch (err) {
    console.error('Error updating user status:', err);
  } finally {
    await client.end();
  }
}

function decodeToken(token) {
  try {
    const decoded = jwt.verify(token, SECRET_KEY);
    if (decoded.exp && decoded.exp < Date.now() / 1000) {
      return null;
    }
    return decoded;
  } catch (err) {
    return null;
  }
}

io.use((socket, next) => {
  const token = socket.handshake.auth.token;
  
  if (!token) {
    return next(new Error('Authentication error'));
  }
  
  const payload = decodeToken(token);
  
  if (!payload) {
    return next(new Error('Invalid token'));
  }
  
  socket.userId = payload.user_id;
  socket.username = payload.username;
  next();
});

io.on('connection', (socket) => {
  console.log(`User connected: ${socket.username} (ID: ${socket.userId})`);
  
  users.set(socket.userId, socket.id);
  
  updateUserStatus(socket.userId, true);
  
  socket.broadcast.emit('user_status', {
    user_id: socket.userId,
    username: socket.username,
    online: true
  });
  
  socket.on('send_message', (data) => {
    const { receiver_id, message, message_id, created_at } = data;
    
    const receiverSocketId = users.get(receiver_id);
    
    if (receiverSocketId) {
      io.to(receiverSocketId).emit('receive_message', {
        id: message_id,
        sender_id: socket.userId,
        sender_username: socket.username,
        receiver_id: receiver_id,
        message: message,
        created_at: created_at
      });
    }
    
    socket.emit('message_sent', {
      id: message_id,
      receiver_id: receiver_id,
      success: true
    });
  });
  
  socket.on('typing', (data) => {
    const { receiver_id } = data;
    const receiverSocketId = users.get(receiver_id);
    
    if (receiverSocketId) {
      io.to(receiverSocketId).emit('user_typing', {
        user_id: socket.userId,
        username: socket.username
      });
    }
  });
  
  socket.on('stop_typing', (data) => {
    const { receiver_id } = data;
    const receiverSocketId = users.get(receiver_id);
    
    if (receiverSocketId) {
      io.to(receiverSocketId).emit('user_stop_typing', {
        user_id: socket.userId
      });
    }
  });
  
  socket.on('disconnect', () => {
    console.log(`User disconnected: ${socket.username} (ID: ${socket.userId})`);
    users.delete(socket.userId);
    
    updateUserStatus(socket.userId, false);
    
    socket.broadcast.emit('user_status', {
      user_id: socket.userId,
      username: socket.username,
      online: false
    });
  });
});

// Health check function for PHP backend
function checkPHPHealth() {
  const healthReq = http.request({
    hostname: 'localhost',
    port: 8081,
    path: '/',
    method: 'GET',
    timeout: 5000
  }, (res) => {
    console.log(`✅ PHP backend health check: ${res.statusCode}`);
  });
  
  healthReq.on('error', (err) => {
    console.error(`❌ PHP backend health check failed:`, err.message);
  });
  
  healthReq.on('timeout', () => {
    console.error(`⏰ PHP backend health check timeout`);
    healthReq.destroy();
  });
  
  healthReq.end();
}

server.listen(PORT, () => {
  console.log(`WebSocket server running on port ${PORT}`);
  
  // Check PHP backend health after 5 seconds
  setTimeout(checkPHPHealth, 5000);
});

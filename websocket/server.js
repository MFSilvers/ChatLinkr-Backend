const http = require('http');
const { Server } = require('socket.io');
const jwt = require('jsonwebtoken');
const { Client } = require('pg');
require('dotenv').config();

const PORT = process.env.WEBSOCKET_PORT || 3001;
const SECRET_KEY = process.env.SESSION_SECRET;

// Validate required environment variables
if (!SECRET_KEY) {
  console.error('❌ Error: SESSION_SECRET environment variable is required');
  process.exit(1);
}

const server = http.createServer();
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

server.listen(PORT, () => {
  console.log(`WebSocket server running on port ${PORT}`);
});

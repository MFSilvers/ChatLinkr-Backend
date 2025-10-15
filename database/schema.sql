-- Database schema for messaging platform

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_online BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT different_users CHECK (sender_id != receiver_id)
);

-- Conversations view (last message per conversation)
CREATE OR REPLACE VIEW conversations AS
SELECT DISTINCT ON (conversation_id)
    CASE 
        WHEN sender_id < receiver_id THEN sender_id || '-' || receiver_id
        ELSE receiver_id || '-' || sender_id
    END as conversation_id,
    CASE 
        WHEN sender_id < receiver_id THEN sender_id
        ELSE receiver_id
    END as user1_id,
    CASE 
        WHEN sender_id < receiver_id THEN receiver_id
        ELSE sender_id
    END as user2_id,
    id as last_message_id,
    message as last_message,
    sender_id,
    receiver_id,
    created_at as last_message_time
FROM messages
ORDER BY conversation_id, created_at DESC;

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id);
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id);
CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

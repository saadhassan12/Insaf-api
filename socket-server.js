const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const admin = require('firebase-admin');

// ── Firebase Admin Init ──────────────────────────────────────────────────────
const serviceAccountPath = path.join(__dirname, 'storage/app/firebase/insafapp-c2502-firebase-adminsdk-1ini1-a07af15cd9.json');
if (fs.existsSync(serviceAccountPath)) {
    admin.initializeApp({
        credential: admin.credential.cert(serviceAccountPath),
    });
    console.log('[FCM] Firebase Admin initialized ✅');
} else {
    console.warn('[FCM] Firebase service account file NOT found — push notifications disabled');
}

/**
 * Send Firebase push notification
 * @param {string} deviceToken
 * @param {string} title
 * @param {string} body
 * @param {object} data  — extra payload (optional)
 */
async function sendPushNotification(deviceToken, title, body, data = {}) {
    if (!deviceToken || !admin.apps.length) return;
    try {
        await admin.messaging().send({
            token: deviceToken,
            notification: { title, body },
            data: data,
            android: { priority: 'high' },
            apns: {
                headers: { 'apns-priority': '10' },
                payload: { aps: { sound: 'default' } },
            },
        });
        console.log(`[FCM] Notification sent to token: ${deviceToken.substring(0, 20)}...`);
    } catch (err) {
        console.warn(`[FCM] Failed to send notification: ${err.message}`);
    }
}

const app = express();
const server = http.createServer(app);

// CORS configuration
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Socket.IO configuration with CORS
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    },
    maxHttpBufferSize: 10e6 // 10MB max file size
});

// Store online users
const onlineUsers = new Map();

// Multer configuration for file uploads
const storage = multer.diskStorage({
    destination: function (req, file, cb) {
        const uploadDir = path.join(__dirname, '../storage/app/public/chat');
        
        // Create directories if they don't exist
        const dirs = ['images', 'voice'];
        dirs.forEach(dir => {
            const fullPath = path.join(uploadDir, dir);
            if (!fs.existsSync(fullPath)) {
                fs.mkdirSync(fullPath, { recursive: true });
            }
        });
        
        let folder = 'images';
        if (file.mimetype.startsWith('audio')) {
            folder = 'voice';
        }
        
        cb(null, path.join(uploadDir, folder));
    },
    filename: function (req, file, cb) {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, uniqueSuffix + path.extname(file.originalname));
    }
});

const upload = multer({ 
    storage: storage,
    limits: { fileSize: 10 * 1024 * 1024 } // 10MB limit
});

// Socket.IO connection handling
io.on('connection', (socket) => {
    console.log('New client connected:', socket.id);

    // User joins - store user info
    socket.on('user:join', (data) => {
        const { userId, userName, deviceToken } = data;
        
        onlineUsers.set(userId, {
            socketId: socket.id,
            userName: userName,
            userId: userId,
            deviceToken: deviceToken || null   // store device token
        });
        
        socket.userId = userId;
        
        console.log(`User ${userName} (ID: ${userId}) joined`);
        
        // Broadcast online users to all clients
        io.emit('users:online', Array.from(onlineUsers.values()));
    });

    // Join a group room
    socket.on('group:join', (data) => {
        const { groupId, userId, userName } = data;
        
        socket.join(`group_${groupId}`);
        console.log(`User ${userName} joined group ${groupId}`);
        
        // Notify other members in the group
        socket.to(`group_${groupId}`).emit('group:user-joined', {
            groupId,
            userId,
            userName,
            message: `${userName} joined the group`
        });
    });

    // Leave a group room
    socket.on('group:leave', (data) => {
        const { groupId, userId, userName } = data;
        
        socket.leave(`group_${groupId}`);
        console.log(`User ${userName} left group ${groupId}`);
        
        // Notify other members
        socket.to(`group_${groupId}`).emit('group:user-left', {
            groupId,
            userId,
            userName,
            message: `${userName} left the group`
        });
    });

    // Send message to group
    socket.on('message:send', (data) => {
        const {
            groupId,
            message,
            userId,
            userName,
            messageType,
            file_url,
            fileUrl,
            id,
            messageId,
            groupName,           // Flutter should send group name
            memberDeviceTokens   // Flutter should send array of {userId, deviceToken} for all members
        } = data;

        // Accept both file_url and fileUrl (Flutter may send either)
        const resolvedFileUrl = file_url || fileUrl || null;

        const messageData = {
            id: id || messageId || Date.now(),
            groupId,
            userId,
            userName,
            messageType: messageType || 'text',
            message: message || null,
            file_url: resolvedFileUrl,
            timestamp: new Date().toISOString(),
            isRead: false
        };

        // Broadcast to ALL users in the group including sender
        io.to(`group_${groupId}`).emit('message:received', messageData);
        console.log(`Message sent to group ${groupId} by ${userName} | type: ${messageData.messageType} | file_url: ${file_url ?? 'none'}`);

        // ── Firebase Push Notifications ──────────────────────────────────
        // Build notification text (WhatsApp style)
        const notifTitle = groupName || 'Group Chat';
        let notifBody;
        if (!messageType || messageType === 'text') {
            notifBody = `${userName}: ${message || ''}`;
        } else if (messageType === 'image') {
            notifBody = `${userName}: 📷 Photo`;
        } else if (messageType === 'voice') {
            notifBody = `${userName}: 🎤 Voice message`;
        } else {
            notifBody = `${userName}: 📎 File`;
        }

        // Option 1: Use device tokens sent by Flutter in the event payload
        if (Array.isArray(memberDeviceTokens) && memberDeviceTokens.length > 0) {
            memberDeviceTokens.forEach(({ userId: memberId, deviceToken }) => {
                if (memberId != userId && deviceToken) {
                    sendPushNotification(deviceToken, notifTitle, notifBody, {
                        groupId: String(groupId),
                        messageType: messageType || 'text',
                        senderId: String(userId),
                    });
                }
            });
        } else {
            // Option 2: Use device tokens stored in onlineUsers map (from user:join)
            onlineUsers.forEach((onlineUser, onlineUserId) => {
                if (onlineUserId != userId && onlineUser.deviceToken) {
                    // We can't know if they're in this group from socket alone,
                    // so we rely on Flutter passing memberDeviceTokens instead
                }
            });
        }
    });

    // Update (edit) message — same pattern as message:send
    socket.on('message:update', (data) => {
        const {
            groupId,
            messageId,
            id,
            userId,
            userName,
            message
        } = data;

        const updatedData = {
            id: id || messageId,
            messageId: id || messageId,
            groupId,
            userId,
            userName,
            message: message || null,
            timestamp: new Date().toISOString()
        };

        // Broadcast to all EXCEPT sender (sender already updated locally)
        socket.to(`group_${groupId}`).emit('message:updated', updatedData);

        console.log(`Message updated in group ${groupId} by ${userName} | messageId: ${updatedData.messageId}`);
    });

    // User is typing
    socket.on('typing:start', (data) => {
        const { groupId, userId, userName } = data;
        
        socket.to(`group_${groupId}`).emit('typing:user', {
            groupId,
            userId,
            userName,
            isTyping: true
        });
    });

    // User stopped typing
    socket.on('typing:stop', (data) => {
        const { groupId, userId, userName } = data;
        
        socket.to(`group_${groupId}`).emit('typing:user', {
            groupId,
            userId,
            userName,
            isTyping: false
        });
    });

    // Message delivered
    socket.on('message:delivered', (data) => {
        const { messageId, groupId, userId } = data;
        
        io.to(`group_${groupId}`).emit('message:status', {
            messageId,
            status: 'delivered',
            userId
        });
    });

    // Message read
    socket.on('message:read', (data) => {
        const { messageId, groupId, userId } = data;
        
        io.to(`group_${groupId}`).emit('message:status', {
            messageId,
            status: 'read',
            userId
        });
    });

    // Delete message
    socket.on('message:delete', (data) => {
        const { messageId, groupId, userId } = data;

        // Emit to ALL in group including sender so everyone removes it
        io.to(`group_${groupId}`).emit('message:deleted', {
            messageId,   // same key Flutter uses to match & remove message
            groupId,
            userId,
            deleted_message_id: messageId  // extra key for safety
        });

        console.log(`Message ${messageId} deleted in group ${groupId} by user ${userId}`);
    });

    // Group updated
    socket.on('group:updated', (data) => {
        const { groupId, groupData } = data;
        
        io.to(`group_${groupId}`).emit('group:info-updated', {
            groupId,
            groupData
        });
    });

    // Member added to group
    socket.on('group:member-added', (data) => {
        const { groupId, member } = data;
        
        io.to(`group_${groupId}`).emit('group:new-member', {
            groupId,
            member
        });
    });

    // Member removed from group
    socket.on('group:member-removed', (data) => {
        const { groupId, memberId, memberName } = data;
        
        io.to(`group_${groupId}`).emit('group:member-left', {
            groupId,
            memberId,
            memberName
        });
    });

    // Disconnect
    socket.on('disconnect', () => {
        if (socket.userId) {
            onlineUsers.delete(socket.userId);
            
            // Broadcast updated online users
            io.emit('users:online', Array.from(onlineUsers.values()));
            
            console.log(`User ${socket.userId} disconnected`);
        }
        console.log('Client disconnected:', socket.id);
    });

    // Error handling
    socket.on('error', (error) => {
        console.error('Socket error:', error);
    });

    // ════════════════════════════════════════════════════════════════
    //  DIRECT CHAT  —  Guest ↔ Lawyer (1-to-1 after accept)
    //  Room name: direct_{guestId}
    // ════════════════════════════════════════════════════════════════

    // Join direct chat room
    socket.on('direct:join', (data) => {
        const { guestId, userId, userName } = data;
        const room = `direct_${guestId}`;
        socket.join(room);
        console.log(`[Direct] ${userName} (${userId}) joined room ${room}`);
    });

    // Leave direct chat room
    socket.on('direct:leave', (data) => {
        const { guestId, userId, userName } = data;
        socket.leave(`direct_${guestId}`);
        console.log(`[Direct] ${userName} (${userId}) left room direct_${guestId}`);
    });

    // Send direct message  →  broadcast to room (including sender for confirmation)
    socket.on('direct:message:send', (data) => {
        const {
            guestId,
            senderId,
            senderName,
            receiverId,
            messageType,
            message,
            file_url,
            id,
            messageId,
            receiverDeviceToken   // Flutter should send receiver's device token
        } = data;

        const msgData = {
            id:          id || messageId || Date.now(),
            guestId,
            senderId,
            senderName,
            receiverId,
            messageType: messageType || 'text',
            message:     message  || null,
            file_url:    file_url || null,
            timestamp:   new Date().toISOString(),
            isRead:      false
        };

        // Send to all in room (both guest & lawyer)
        io.to(`direct_${guestId}`).emit('direct:message:received', msgData);
        console.log(`[Direct] msg in direct_${guestId} from ${senderName} | type: ${msgData.messageType}`);

        // ── Firebase Push Notification to receiver ───────────────────────
        let notifBody;
        if (!messageType || messageType === 'text') {
            notifBody = message || 'Sent a message';
        } else if (messageType === 'image') {
            notifBody = '📷 Photo';
        } else if (messageType === 'voice') {
            notifBody = '🎤 Voice message';
        } else {
            notifBody = '📎 File';
        }

        // Option 1: token sent directly in event
        const tokenFromEvent = receiverDeviceToken;
        // Option 2: token stored from user:join
        const receiverOnline = onlineUsers.get(String(receiverId)) || onlineUsers.get(receiverId);
        const tokenFromOnline = receiverOnline?.deviceToken;

        const finalToken = tokenFromEvent || tokenFromOnline;
        if (finalToken) {
            sendPushNotification(finalToken, senderName, notifBody, {
                guestId:     String(guestId),
                messageType: messageType || 'text',
                senderId:    String(senderId),
            });
        }
    });

    // Update direct message
    socket.on('direct:message:update', (data) => {
        const { guestId, messageId, id, senderId, senderName, message } = data;
        const resolvedId = id || messageId;

        const updatedData = {
            id:        resolvedId,
            messageId: resolvedId,
            guestId,
            senderId,
            senderName,
            message:   message || null,
            timestamp: new Date().toISOString()
        };

        // Broadcast to others only (sender updated locally)
        socket.to(`direct_${guestId}`).emit('direct:message:updated', updatedData);
        console.log(`[Direct] msg updated in direct_${guestId} | messageId: ${resolvedId}`);
    });

    // Delete direct message
    socket.on('direct:message:delete', (data) => {
        const { guestId, messageId, senderId } = data;

        // Broadcast to all in room including sender
        io.to(`direct_${guestId}`).emit('direct:message:deleted', {
            messageId,
            guestId,
            senderId
        });
        console.log(`[Direct] msg deleted in direct_${guestId} | messageId: ${messageId}`);
    });

    // Typing in direct chat
    socket.on('direct:typing:start', (data) => {
        const { guestId, userId, userName } = data;
        socket.to(`direct_${guestId}`).emit('direct:typing:user', {
            guestId, userId, userName, isTyping: true
        });
    });

    socket.on('direct:typing:stop', (data) => {
        const { guestId, userId, userName } = data;
        socket.to(`direct_${guestId}`).emit('direct:typing:user', {
            guestId, userId, userName, isTyping: false
        });
    });
});

// REST endpoints for testing
app.get('/', (req, res) => {
    res.json({
        success: true,
        message: 'Socket.IO Chat Server is running',
        timestamp: new Date().toISOString(),
        onlineUsers: onlineUsers.size
    });
});

app.get('/online-users', (req, res) => {
    res.json({
        success: true,
        onlineUsers: Array.from(onlineUsers.values())
    });
});

// File upload endpoint for testing
app.post('/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({
            success: false,
            message: 'No file uploaded'
        });
    }
    
    res.json({
        success: true,
        message: 'File uploaded successfully',
        file: {
            filename: req.file.filename,
            originalname: req.file.originalname,
            size: req.file.size,
            mimetype: req.file.mimetype,
            path: req.file.path
        }
    });
});

// Server configuration
const PORT = process.env.SOCKET_PORT || 3000;

server.listen(PORT, () => {
    console.log(`===========================================`);
    console.log(`Socket.IO Chat Server Running`);
    console.log(`Port: ${PORT}`);
    console.log(`Time: ${new Date().toLocaleString()}`);
    console.log(`===========================================`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
    console.log('SIGTERM signal received: closing HTTP server');
    server.close(() => {
        console.log('HTTP server closed');
    });
});

module.exports = { app, io };

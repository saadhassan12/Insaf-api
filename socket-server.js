const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

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
        const { userId, userName } = data;
        
        onlineUsers.set(userId, {
            socketId: socket.id,
            userName: userName,
            userId: userId
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
        const { groupId, message, userId, userName, messageType, fileUrl, fileName } = data;
        
        const messageData = {
            id: Date.now(), // Temporary ID, should be from database
            groupId,
            userId,
            userName,
            messageType,
            message: message || null,
            fileUrl: fileUrl || null,
            fileName: fileName || null,
            timestamp: new Date().toISOString(),
            isRead: false
        };
        
        // Broadcast message to all users in the group including sender
        io.to(`group_${groupId}`).emit('message:received', messageData);
        
        console.log(`Message sent to group ${groupId} by ${userName}`);
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
        const { messageId, groupId } = data;
        
        io.to(`group_${groupId}`).emit('message:deleted', {
            messageId,
            groupId
        });
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

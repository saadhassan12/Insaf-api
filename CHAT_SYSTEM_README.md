# Lawyer Group Chat System - Complete Implementation

## 🎯 Overview
A complete WhatsApp-like group chat system for lawyers built with Laravel (REST API) and Socket.IO (Real-time communication). This system allows lawyers to create groups, add members, and communicate via text, images, and voice notes.

## ✨ Features

### Group Management
- ✅ Create chat groups (lawyers only)
- ✅ Add/remove members
- ✅ Update group details and image
- ✅ Delete groups (creator only)
- ✅ Leave groups
- ✅ Admin/member roles

### Messaging
- ✅ Text messages
- ✅ Image sharing
- ✅ Voice notes
- ✅ Message deletion
- ✅ Read/unread status
- ✅ Message pagination

### Real-time Features (Socket.IO)
- ✅ Real-time message delivery
- ✅ Online/offline status
- ✅ Typing indicators
- ✅ Message status (delivered/read)
- ✅ Group notifications

## 📋 Prerequisites

- PHP >= 8.2
- MySQL >= 8.0
- Node.js >= 16.x
- Composer
- npm

## 🚀 Installation & Setup

### 1. Database Setup
The migrations have already been run. Tables created:
- `chat_groups`
- `chat_group_members`
- `chat_messages`

### 2. Storage Setup
Create symbolic link for file storage:
```bash
php artisan storage:link
```

### 3. Install Dependencies
All dependencies are already installed:
- Laravel Passport (for authentication)
- Socket.IO (for real-time)
- Express.js (for Socket.IO server)
- Multer (for file uploads)

## 🎮 Running the Application

### Start Laravel Server
```bash
php artisan serve
```
The API will be available at: `http://localhost:8000`

### Start Socket.IO Server
```bash
npm run socket
```
The Socket.IO server will run on: `http://localhost:3000`

### Run Both Servers Simultaneously
```bash
npm run dev:all
```

## 📚 API Documentation

Complete API documentation is available in: `CHAT_API_DOCUMENTATION.md`

### Quick API Reference

#### Authentication
All endpoints require Bearer token authentication:
```
Authorization: Bearer {your_access_token}
```

#### Main Endpoints

**Group Management**
- `POST /api/chat/groups/create` - Create group
- `GET /api/chat/groups` - Get all groups
- `GET /api/chat/groups/{id}` - Get group details
- `PUT /api/chat/groups/{id}` - Update group
- `DELETE /api/chat/groups/{id}` - Delete group

**Members**
- `POST /api/chat/groups/{id}/add-members` - Add members
- `DELETE /api/chat/groups/{id}/remove-member/{userId}` - Remove member
- `POST /api/chat/groups/{id}/leave` - Leave group

**Messages**
- `POST /api/chat/groups/{id}/messages` - Send message
- `GET /api/chat/groups/{id}/messages` - Get messages
- `DELETE /api/chat/messages/{id}` - Delete message
- `POST /api/chat/groups/{id}/mark-read` - Mark as read

**Utilities**
- `GET /api/chat/lawyers` - Get all lawyers

## 🧪 Testing with Postman

### Import Postman Collection
1. Open Postman
2. Click Import
3. Select `Postman_Collection_Chat_API.json`
4. Update the `access_token` variable with your token

### Testing Flow
1. Login to get access token
2. Get lawyers list
3. Create a group
4. Send messages (text/image/voice)
5. Get messages
6. Add/remove members

### Sample Requests

**Create Group**
```
POST http://localhost:8000/api/chat/groups/create
Content-Type: multipart/form-data

name: "Legal Team"
description: "Corporate law team"
member_ids[0]: 2
member_ids[1]: 3
group_image: [file]
```

**Send Text Message**
```
POST http://localhost:8000/api/chat/groups/1/messages
Content-Type: multipart/form-data

message_type: text
message: "Hello team!"
```

**Send Image**
```
POST http://localhost:8000/api/chat/groups/1/messages
Content-Type: multipart/form-data

message_type: image
message: "Check this out"
file: [image file]
```

## 🔌 Socket.IO Testing

### Using Browser Test Page
1. Start Socket.IO server: `npm run socket`
2. Open browser: `http://localhost:8000/socket-test.html`
3. Enter your user details
4. Click "Connect"
5. Join a group
6. Send messages in real-time

### Using Browser Console
```javascript
// Connect
const socket = io('http://localhost:3000');

// Join as user
socket.emit('user:join', {
    userId: 1,
    userName: 'John Doe'
});

// Join group
socket.emit('group:join', {
    groupId: 1,
    userId: 1,
    userName: 'John Doe'
});

// Send message
socket.emit('message:send', {
    groupId: 1,
    userId: 1,
    userName: 'John Doe',
    messageType: 'text',
    message: 'Hello!'
});

// Listen for messages
socket.on('message:received', (data) => {
    console.log('New message:', data);
});

// Listen for online users
socket.on('users:online', (users) => {
    console.log('Online users:', users);
});
```

## 📁 File Structure

```
Insaf Api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── ChatController.php        # Main chat controller
│   └── Models/
│       ├── ChatGroup.php                 # Group model
│       ├── ChatGroupMember.php           # Member model
│       └── ChatMessage.php               # Message model
├── database/
│   └── migrations/
│       ├── *_create_chat_groups_table.php
│       ├── *_create_chat_group_members_table.php
│       └── *_create_chat_messages_table.php
├── routes/
│   └── api.php                           # API routes
├── storage/
│   └── app/
│       └── public/
│           └── chat/
│               ├── groups/               # Group images
│               ├── images/               # Message images
│               └── voice/                # Voice notes
├── public/
│   └── socket-test.html                  # Socket.IO test page
├── socket-server.js                      # Socket.IO server
├── CHAT_API_DOCUMENTATION.md             # Complete API docs
├── Postman_Collection_Chat_API.json      # Postman collection
└── package.json                          # Node dependencies
```

## 🔐 Security Features

- ✅ Authentication required for all endpoints
- ✅ Only lawyers can create and join groups
- ✅ Role-based permissions (admin/member)
- ✅ File upload validation
- ✅ Group membership verification
- ✅ Owner-only deletion

## 📝 Database Schema

### chat_groups
- id, created_by, name, description, group_image, status, timestamps

### chat_group_members
- id, group_id, user_id, role (admin/member), joined_at, timestamps

### chat_messages
- id, group_id, user_id, message_type (text/image/voice), message, file_path, file_name, file_size, mime_type, is_read, timestamps

## 🎨 Message Types

1. **Text Messages**
   - Simple text communication
   - Required: message_type=text, message

2. **Image Messages**
   - Share images (JPEG, PNG, GIF)
   - Max size: 10MB
   - Required: message_type=image, file

3. **Voice Notes**
   - Share audio messages
   - Max size: 10MB
   - Required: message_type=voice, file

## 🌐 Socket.IO Events Reference

### Client → Server
- `user:join` - Join socket server
- `group:join` - Join group room
- `group:leave` - Leave group room
- `message:send` - Send message
- `typing:start` - Start typing
- `typing:stop` - Stop typing
- `message:read` - Mark as read

### Server → Client
- `users:online` - Online users list
- `message:received` - New message
- `group:user-joined` - User joined group
- `group:user-left` - User left group
- `typing:user` - Typing status
- `message:status` - Message status
- `message:deleted` - Message deleted

## 🔧 Configuration

### Socket.IO Port
Default: 3000
Change in `socket-server.js`:
```javascript
const PORT = process.env.SOCKET_PORT || 3000;
```

### File Upload Limits
Default: 10MB
Change in `ChatController.php` and `socket-server.js`

## 🐛 Troubleshooting

### Socket.IO not connecting
1. Check if server is running: `http://localhost:3000`
2. Verify CORS settings in `socket-server.js`
3. Check browser console for errors

### File uploads failing
1. Run: `php artisan storage:link`
2. Check permissions: `storage/app/public/chat/`
3. Verify file size limits

### Authentication errors
1. Ensure valid Bearer token
2. Check token expiration
3. Verify user role is 'lawyer'

## 📱 Testing Checklist

- [ ] Create group with image
- [ ] Add multiple members
- [ ] Send text message
- [ ] Send image message
- [ ] Send voice note
- [ ] Get messages (paginated)
- [ ] Update group details
- [ ] Remove member
- [ ] Leave group
- [ ] Delete message
- [ ] Delete group
- [ ] Real-time messaging via Socket.IO
- [ ] Typing indicators
- [ ] Online/offline status

## 🚀 Production Deployment

### Environment Variables
Add to `.env`:
```
SOCKET_PORT=3000
SOCKET_URL=https://your-domain.com
```

### PM2 Process Manager (recommended)
```bash
npm install -g pm2
pm2 start socket-server.js --name chat-socket
pm2 save
pm2 startup
```

### Nginx Configuration
```nginx
# Socket.IO proxy
location /socket.io/ {
    proxy_pass http://localhost:3000;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

## 📄 License
This project is part of the Insaf App backend system.

## 👨‍💻 Support
For issues or questions, refer to `CHAT_API_DOCUMENTATION.md` for detailed API documentation.

---

**Created Date**: January 13, 2026
**Status**: ✅ Complete and Ready for Testing

# 🎯 QUICK START GUIDE

## 🚀 Start in 3 Steps

### Step 1: Start Servers
```bash
# Terminal 1 - Laravel API
php artisan serve

# Terminal 2 - Socket.IO Server  
npm run socket
```

### Step 2: Import Postman Collection
1. Open Postman
2. Import: `Postman_Collection_Chat_API.json`
3. Set variable `access_token` = Your Bearer token

### Step 3: Test!
Open Postman and run requests in this order:
1. Get Lawyers
2. Create Group
3. Send Message

---

## 📍 Quick Access URLs

- **Laravel API**: http://localhost:8000/api
- **Socket.IO Server**: http://localhost:3000
- **Test Page**: http://localhost:8000/socket-test.html
- **Socket Status**: http://localhost:3000/

---

## 🎮 Test Real-time Chat Now!

1. Open: http://localhost:8000/socket-test.html
2. Enter:
   - User ID: 1
   - User Name: Your Name
3. Click "Connect"
4. Enter Group ID: 1
5. Click "Join Group"
6. Type message and click "Send Message"

---

## 📋 API Endpoints Quick Reference

### Groups
```
POST   /api/chat/groups/create          Create group
GET    /api/chat/groups                 List all groups
GET    /api/chat/groups/{id}            Group details
PUT    /api/chat/groups/{id}            Update group
DELETE /api/chat/groups/{id}            Delete group
POST   /api/chat/groups/{id}/leave      Leave group
```

### Members
```
POST   /api/chat/groups/{id}/add-members           Add members
DELETE /api/chat/groups/{id}/remove-member/{uid}   Remove member
```

### Messages
```
POST   /api/chat/groups/{id}/messages    Send message
GET    /api/chat/groups/{id}/messages    Get messages
DELETE /api/chat/messages/{id}           Delete message
POST   /api/chat/groups/{id}/mark-read   Mark as read
```

### Utilities
```
GET    /api/chat/lawyers                 Get all lawyers
```

---

## 💬 Send Message Examples

### Text Message (Postman)
```
POST /api/chat/groups/1/messages
Content-Type: multipart/form-data

message_type: text
message: "Hello everyone!"
```

### Image Message (Postman)
```
POST /api/chat/groups/1/messages
Content-Type: multipart/form-data

message_type: image
message: "Check this out"
file: [Select Image File]
```

### Voice Message (Postman)
```
POST /api/chat/groups/1/messages
Content-Type: multipart/form-data

message_type: voice
file: [Select Audio File]
```

---

## 🔌 Socket.IO Quick Test

### Browser Console
```javascript
// Connect
const socket = io('http://localhost:3000');

// Join
socket.emit('user:join', {userId: 1, userName: 'Test'});
socket.emit('group:join', {groupId: 1, userId: 1, userName: 'Test'});

// Send
socket.emit('message:send', {
    groupId: 1, userId: 1, userName: 'Test',
    messageType: 'text', message: 'Hi!'
});

// Listen
socket.on('message:received', (data) => console.log('📨', data));
socket.on('users:online', (users) => console.log('👥', users));
```

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `PROJECT_DELIVERY.md` | Complete delivery summary |
| `CHAT_API_DOCUMENTATION.md` | Full API reference |
| `CHAT_SYSTEM_README.md` | System overview |
| `TESTING_GUIDE.md` | Detailed testing guide |
| `QUICK_START.md` | This file |

---

## ✅ Verify Everything Works

### 1. Check Servers
```bash
# Laravel API
curl http://localhost:8000/api

# Socket.IO
curl http://localhost:3000
```

### 2. Check Database
```sql
SHOW TABLES LIKE 'chat_%';
-- Should show: chat_groups, chat_group_members, chat_messages
```

### 3. Check Storage
```bash
ls -la public/storage
# Should show: chat link
```

---

## 🎯 Common First Tests

### Test 1: Get Lawyers
```
GET /api/chat/lawyers
Authorization: Bearer YOUR_TOKEN
```
✅ Should return list of lawyers

### Test 2: Create Group
```
POST /api/chat/groups/create
Authorization: Bearer YOUR_TOKEN
Body: name="Test Group", member_ids[]=[2,3]
```
✅ Should create group and return group ID

### Test 3: Send Message
```
POST /api/chat/groups/1/messages
Authorization: Bearer YOUR_TOKEN
Body: message_type="text", message="Hello!"
```
✅ Should send message and return message data

---

## 🐛 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| 401 Unauthorized | Add Bearer token to Authorization header |
| 403 Forbidden | Ensure user role is 'lawyer' |
| Socket won't connect | Check if `npm run socket` is running |
| File URL returns 404 | Run `php artisan storage:link` |
| Can't create group | Verify you're authenticated as lawyer |

---

## 📞 Need Help?

1. Check `TESTING_GUIDE.md` for detailed walkthroughs
2. Check `CHAT_API_DOCUMENTATION.md` for API details
3. Check console logs for errors
4. Verify all servers are running

---

## 🎉 That's It!

You're ready to test the complete lawyer group chat system!

**Happy Testing! 🚀**


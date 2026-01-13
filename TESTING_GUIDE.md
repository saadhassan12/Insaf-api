# 🧪 Testing Guide - Lawyer Group Chat System

## ✅ System Status

### Servers Running
- ✅ **Laravel API**: http://localhost:8000
- ✅ **Socket.IO Server**: http://localhost:3000

### Database
- ✅ Migrations completed
- ✅ Tables created: `chat_groups`, `chat_group_members`, `chat_messages`
- ✅ Storage link created

---

## 📦 What Has Been Implemented

### Backend Components
1. ✅ **3 Database Tables** with proper relationships and foreign keys
2. ✅ **3 Eloquent Models** (ChatGroup, ChatGroupMember, ChatMessage)
3. ✅ **Complete REST API** with 13 endpoints
4. ✅ **Socket.IO Real-time Server** with 10+ event handlers
5. ✅ **File Upload System** for images and voice notes
6. ✅ **Authentication & Authorization** (Laravel Passport)
7. ✅ **Role-based Access Control** (admin/member)

### Features Implemented
- ✅ Group creation with image upload
- ✅ Add/remove members (admin only)
- ✅ Send text, image, and voice messages
- ✅ Real-time message delivery via Socket.IO
- ✅ Online/offline user status
- ✅ Typing indicators
- ✅ Message read/unread status
- ✅ Message deletion
- ✅ Group management (update, delete, leave)
- ✅ Pagination for messages
- ✅ Search lawyers functionality

---

## 🚀 Quick Start Testing

### Step 1: Verify Servers Are Running

**Check Laravel API**
```bash
php artisan serve
```
Visit: http://localhost:8000

**Check Socket.IO Server**
```bash
npm run socket
```
Should show: "Socket.IO Chat Server Running - Port: 3000"

---

### Step 2: Get Authentication Token

You need a valid Bearer token from an existing lawyer user.

**Method 1: Use existing login API**
```
POST http://localhost:8000/api/login
Content-Type: application/json

{
    "mobile_number": "your_mobile",
    "password": "your_password"
}
```

**Method 2: Check if you have a user in database**
Query your users table for a user with `role = 'lawyer'`

---

### Step 3: Import Postman Collection

1. Open Postman
2. Click **Import**
3. Select file: `Postman_Collection_Chat_API.json`
4. Update **Collection Variables**:
   - `access_token` = Your Bearer token
   - `base_url` = http://localhost:8000/api
   - `socket_url` = http://localhost:3000

---

## 📝 Complete Testing Workflow

### Test 1: Get Available Lawyers ✅

**Request**
```
GET http://localhost:8000/api/chat/lawyers
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Lawyers retrieved successfully",
    "data": [
        {
            "id": 2,
            "first_name": "John",
            "last_name": "Doe",
            "mobile_number": "1234567890",
            "license_no": "LAW123"
        }
    ]
}
```

**What to note**: Copy some user IDs to add to your group

---

### Test 2: Create a Group ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/create
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
- name: "Test Legal Group"
- description: "Testing the chat system"
- group_image: [Upload an image file - optional]
- member_ids[0]: 2
- member_ids[1]: 3
```

**Expected Response**
```json
{
    "success": true,
    "message": "Group created successfully",
    "data": {
        "group": {
            "id": 1,
            "name": "Test Legal Group",
            "description": "Testing the chat system",
            "status": "active",
            "created_by": 1,
            "creator": {...},
            "members": [...]
        },
        "group_image_url": "http://localhost:8000/storage/chat/groups/..."
    }
}
```

**What to note**: Copy the `group.id` (you'll use this in next tests)

---

### Test 3: Get All Your Groups ✅

**Request**
```
GET http://localhost:8000/api/chat/groups
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Groups retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Test Legal Group",
            "members_count": 3,
            "latest_message": null,
            "created_at": "2026-01-13T...",
            ...
        }
    ]
}
```

---

### Test 4: Send Text Message ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/1/messages
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
- message_type: text
- message: "Hello everyone! This is our first message."
```

**Expected Response**
```json
{
    "success": true,
    "message": "Message sent successfully",
    "data": {
        "message": {
            "id": 1,
            "group_id": 1,
            "user_id": 1,
            "message_type": "text",
            "message": "Hello everyone! This is our first message.",
            "file_path": null,
            "is_read": false,
            "created_at": "2026-01-13T...",
            "user": {
                "id": 1,
                "first_name": "...",
                "last_name": "..."
            }
        },
        "file_url": null
    }
}
```

---

### Test 5: Send Image Message ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/1/messages
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
- message_type: image
- message: "Check out this document" [optional]
- file: [Upload an image file - JPG, PNG, GIF]
```

**Expected Response**
```json
{
    "success": true,
    "message": "Message sent successfully",
    "data": {
        "message": {
            "id": 2,
            "message_type": "image",
            "file_path": "chat/images/1705234567_abc.jpg",
            "file_name": "document.jpg",
            "file_size": 123456,
            "mime_type": "image/jpeg",
            ...
        },
        "file_url": "http://localhost:8000/storage/chat/images/1705234567_abc.jpg"
    }
}
```

**What to note**: The file_url is accessible via browser

---

### Test 6: Send Voice Note ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/1/messages
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
- message_type: voice
- file: [Upload an audio file - MP3, WAV, M4A, etc.]
```

**Expected Response**
```json
{
    "success": true,
    "message": "Message sent successfully",
    "data": {
        "message": {
            "id": 3,
            "message_type": "voice",
            "file_path": "chat/voice/1705234567_voice.mp3",
            ...
        },
        "file_url": "http://localhost:8000/storage/chat/voice/..."
    }
}
```

---

### Test 7: Get Messages ✅

**Request**
```
GET http://localhost:8000/api/chat/groups/1/messages?per_page=20&page=1
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Messages retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 3,
                "message_type": "voice",
                "message": null,
                "file_url": "http://localhost:8000/storage/...",
                "created_at": "2026-01-13T...",
                "user": {...}
            },
            {
                "id": 2,
                "message_type": "image",
                ...
            },
            {
                "id": 1,
                "message_type": "text",
                ...
            }
        ],
        "per_page": 20,
        "total": 3
    }
}
```

**What to note**: Messages are ordered newest first

---

### Test 8: Add Members to Group ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/1/add-members
Authorization: Bearer {your_token}
Content-Type: application/json

Body (raw JSON):
{
    "member_ids": [4, 5, 6]
}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Members processed",
    "data": {
        "added_members": [
            {"id": 4, "first_name": "Alice", ...},
            {"id": 5, "first_name": "Bob", ...}
        ],
        "already_members": [6]
    }
}
```

---

### Test 9: Update Group Details ✅

**Request (Postman)**
```
POST http://localhost:8000/api/chat/groups/1
Authorization: Bearer {your_token}
Content-Type: multipart/form-data

Body (form-data):
- _method: PUT
- name: "Updated Legal Group Name"
- description: "Updated description"
- group_image: [Upload new image - optional]
```

**Expected Response**
```json
{
    "success": true,
    "message": "Group updated successfully",
    "data": {
        "group": {
            "id": 1,
            "name": "Updated Legal Group Name",
            "description": "Updated description",
            ...
        }
    }
}
```

**Note**: Only group admin can update

---

### Test 10: Mark Messages as Read ✅

**Request**
```
POST http://localhost:8000/api/chat/groups/1/mark-read
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Messages marked as read"
}
```

---

### Test 11: Delete a Message ✅

**Request**
```
DELETE http://localhost:8000/api/chat/messages/1
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Message deleted successfully"
}
```

**Note**: Only sender or group admin can delete

---

### Test 12: Remove Member from Group ✅

**Request**
```
DELETE http://localhost:8000/api/chat/groups/1/remove-member/5
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Member removed successfully"
}
```

**Note**: Only admin can remove members

---

### Test 13: Leave Group ✅

**Request**
```
POST http://localhost:8000/api/chat/groups/1/leave
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Left group successfully"
}
```

**Note**: Creator cannot leave, must delete group instead

---

### Test 14: Delete Group ✅

**Request**
```
DELETE http://localhost:8000/api/chat/groups/1
Authorization: Bearer {your_token}
```

**Expected Response**
```json
{
    "success": true,
    "message": "Group deleted successfully"
}
```

**Note**: Only creator can delete group

---

## 🔌 Socket.IO Real-time Testing

### Method 1: Using Browser Test Page

1. **Open test page**
   - Visit: http://localhost:8000/socket-test.html

2. **Configure connection**
   - Socket URL: http://localhost:3000
   - User ID: 1 (your user ID)
   - User Name: Your Name

3. **Connect**
   - Click "Connect" button
   - You should see "Connected with socket ID: ..."

4. **Join a group**
   - Enter Group ID: 1
   - Click "Join Group"

5. **Send messages**
   - Select message type (text/image/voice)
   - Type your message
   - Click "Send Message"

6. **Test typing indicators**
   - Click "Start Typing"
   - Click "Stop Typing"

7. **Monitor events**
   - Watch the Event Log at the bottom
   - You'll see all real-time events

---

### Method 2: Using Browser Console

1. **Open browser console** (F12)

2. **Add Socket.IO library** (in HTML page or use CDN)
   ```html
   <script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
   ```

3. **Connect and test**
   ```javascript
   // Connect to server
   const socket = io('http://localhost:3000');
   
   // Join as user
   socket.emit('user:join', {
       userId: 1,
       userName: 'Test User'
   });
   
   // Join group
   socket.emit('group:join', {
       groupId: 1,
       userId: 1,
       userName: 'Test User'
   });
   
   // Send message
   socket.emit('message:send', {
       groupId: 1,
       userId: 1,
       userName: 'Test User',
       messageType: 'text',
       message: 'Hello from Socket.IO!'
   });
   
   // Listen for messages
   socket.on('message:received', (data) => {
       console.log('📨 New message:', data);
   });
   
   // Listen for online users
   socket.on('users:online', (users) => {
       console.log('👤 Online users:', users);
   });
   
   // Start typing
   socket.emit('typing:start', {
       groupId: 1,
       userId: 1,
       userName: 'Test User'
   });
   
   // Listen for typing
   socket.on('typing:user', (data) => {
       console.log('✏️ Typing:', data);
   });
   ```

---

### Method 3: Multiple Browser Windows

To test real-time communication:

1. **Open 2 browser windows**
2. **In each window**, open: http://localhost:8000/socket-test.html
3. **Connect both** with different user IDs:
   - Window 1: User ID = 1, Name = "Lawyer One"
   - Window 2: User ID = 2, Name = "Lawyer Two"
4. **Both join same group** (Group ID = 1)
5. **Send message from Window 1**
6. **Watch it appear in Window 2** in real-time!

---

## ✅ Testing Checklist

### REST API Tests
- [ ] Get lawyers list
- [ ] Create group without image
- [ ] Create group with image
- [ ] Get all groups
- [ ] Get specific group details
- [ ] Send text message
- [ ] Send image message (verify file URL works)
- [ ] Send voice message (verify file URL works)
- [ ] Get messages with pagination
- [ ] Add members to group
- [ ] Remove member from group
- [ ] Update group name
- [ ] Update group description
- [ ] Update group image
- [ ] Mark messages as read
- [ ] Delete a message
- [ ] Leave group
- [ ] Delete group

### Socket.IO Tests
- [ ] Connect to Socket.IO server
- [ ] Join as user (verify online status)
- [ ] Join group room
- [ ] Send text message (verify real-time delivery)
- [ ] Test typing indicators
- [ ] Test with multiple clients
- [ ] Leave group room
- [ ] Disconnect

### Error Cases
- [ ] Create group without authentication (should fail)
- [ ] Non-lawyer tries to create group (should fail)
- [ ] Non-admin tries to add members (should fail)
- [ ] Non-admin tries to remove members (should fail)
- [ ] Try to access group you're not member of (should fail)
- [ ] Creator tries to leave group (should fail - must delete)
- [ ] Upload file larger than 10MB (should fail)

---

## 🎯 Expected Behavior

### Group Creation
✅ Only lawyers can create groups
✅ Creator becomes admin automatically
✅ Can add members during creation
✅ Group image is optional

### Messaging
✅ Only group members can send messages
✅ Messages are stored in database
✅ Files are stored in storage/app/public/chat/
✅ File URLs are publicly accessible
✅ Messages ordered by newest first

### Real-time Features
✅ Messages broadcast to all group members
✅ Online status updates instantly
✅ Typing indicators work in real-time
✅ Multiple users can connect simultaneously

### Permissions
✅ Admin can: add/remove members, update group, delete messages
✅ Members can: send messages, leave group, delete own messages
✅ Creator can: delete group, all admin permissions

---

## 🐛 Common Issues & Solutions

### Issue 1: "Unauthenticated" error
**Solution**: Make sure you're sending the Bearer token in Authorization header

### Issue 2: File upload returns null URL
**Solution**: Run `php artisan storage:link`

### Issue 3: Socket.IO not connecting
**Solution**: 
1. Check if server is running: `npm run socket`
2. Visit http://localhost:3000 - should show status
3. Check CORS settings

### Issue 4: "Only lawyers can create groups"
**Solution**: Verify your user's role in database is 'lawyer'

### Issue 5: Cannot see uploaded files
**Solution**: 
1. Check storage/app/public/chat/ directories exist
2. Verify file permissions
3. Check symbolic link exists in public/storage

---

## 📊 Database Verification

### Check if tables exist
```sql
SHOW TABLES LIKE 'chat_%';
```

Should show:
- chat_groups
- chat_group_members
- chat_messages

### Check groups created
```sql
SELECT * FROM chat_groups;
```

### Check messages
```sql
SELECT * FROM chat_messages ORDER BY created_at DESC;
```

### Check group members
```sql
SELECT cgm.*, u.first_name, u.last_name 
FROM chat_group_members cgm
JOIN users u ON cgm.user_id = u.id
WHERE cgm.group_id = 1;
```

---

## 🎉 Success Criteria

You've successfully tested the system when:

1. ✅ You can create a group with multiple members
2. ✅ You can send text, image, and voice messages
3. ✅ Files are uploaded and URLs are accessible
4. ✅ Messages appear in real-time via Socket.IO
5. ✅ You can add/remove members
6. ✅ Typing indicators work
7. ✅ Online status updates in real-time
8. ✅ Permissions work correctly (admin vs member)
9. ✅ All CRUD operations work for groups
10. ✅ Pagination works for messages

---

## 📞 Next Steps

After successful testing:

1. **Document any bugs found**
2. **Test with real users** (multiple lawyers)
3. **Performance testing** with many messages
4. **Security audit**
5. **Deploy to staging** environment

---

## 📚 Additional Resources

- **Full API Documentation**: `CHAT_API_DOCUMENTATION.md`
- **Implementation Details**: `CHAT_SYSTEM_README.md`
- **Postman Collection**: `Postman_Collection_Chat_API.json`
- **Socket Test Page**: `public/socket-test.html`

---

**Happy Testing! 🚀**

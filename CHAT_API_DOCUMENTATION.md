# Chat System API Documentation

## Overview
This is a comprehensive REST API + Socket.IO implementation for a lawyer group chat system. The system supports:
- Group creation and management
- Adding/removing members
- Real-time messaging (text, images, voice notes)
- WhatsApp-like features

---

## Base URLs
- **REST API**: `http://localhost:8000/api`
- **Socket.IO Server**: `http://localhost:3000`

---

## Authentication
All REST API endpoints require authentication using Laravel Passport.

### Headers Required:
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
Accept: application/json
```

For multipart requests (file uploads):
```
Authorization: Bearer {your_access_token}
Content-Type: multipart/form-data
Accept: application/json
```

---

## REST API Endpoints

### 1. Get All Lawyers (For Adding to Groups)

**Endpoint**: `GET /api/chat/lawyers`

**Description**: Get list of all lawyers that can be added to groups

**Query Parameters**:
- `search` (optional): Search by name or mobile number

**Example Request**:
```
GET /api/chat/lawyers?search=john
```

**Example Response**:
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
            "license_no": "LAW12345"
        }
    ]
}
```

---

### 2. Create Group

**Endpoint**: `POST /api/chat/groups/create`

**Description**: Create a new chat group (Only lawyers can create groups)

**Request Body** (multipart/form-data):
```
name: "Legal Team Alpha" (required)
description: "Team for handling corporate cases" (optional)
group_image: [image file] (optional)
member_ids[]: [2, 3, 4] (optional, array of user IDs)
```

**Example using Postman**:
1. Select POST method
2. Enter URL: `http://localhost:8000/api/chat/groups/create`
3. Go to Headers tab, add Authorization header
4. Go to Body tab, select "form-data"
5. Add fields:
   - name: "Legal Team Alpha"
   - description: "Team for handling corporate cases"
   - group_image: [select image file]
   - member_ids[0]: 2
   - member_ids[1]: 3

**Example Response**:
```json
{
    "success": true,
    "message": "Group created successfully",
    "data": {
        "group": {
            "id": 1,
            "created_by": 1,
            "name": "Legal Team Alpha",
            "description": "Team for handling corporate cases",
            "group_image": "chat/groups/1705234567_abc123.jpg",
            "status": "active",
            "created_at": "2026-01-13T18:30:00.000000Z",
            "updated_at": "2026-01-13T18:30:00.000000Z",
            "creator": {
                "id": 1,
                "first_name": "Jane",
                "last_name": "Smith"
            },
            "members": [...],
            "users": [...]
        },
        "group_image_url": "http://localhost:8000/storage/chat/groups/1705234567_abc123.jpg"
    }
}
```

---

### 3. Get All Groups

**Endpoint**: `GET /api/chat/groups`

**Description**: Get all groups where the authenticated user is a member

**Example Response**:
```json
{
    "success": true,
    "message": "Groups retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Legal Team Alpha",
            "description": "Team for handling corporate cases",
            "group_image": "http://localhost:8000/storage/chat/groups/image.jpg",
            "status": "active",
            "created_by": {
                "id": 1,
                "first_name": "Jane",
                "last_name": "Smith"
            },
            "members_count": 5,
            "latest_message": {
                "id": 10,
                "message": "Hello team",
                "created_at": "2026-01-13T18:30:00.000000Z",
                "user": {...}
            },
            "created_at": "2026-01-13T18:00:00.000000Z",
            "updated_at": "2026-01-13T18:30:00.000000Z"
        }
    ]
}
```

---

### 4. Get Group Details

**Endpoint**: `GET /api/chat/groups/{groupId}`

**Description**: Get detailed information about a specific group

**Example Request**:
```
GET /api/chat/groups/1
```

**Example Response**:
```json
{
    "success": true,
    "message": "Group details retrieved successfully",
    "data": {
        "id": 1,
        "name": "Legal Team Alpha",
        "description": "Team for handling corporate cases",
        "group_image": "http://localhost:8000/storage/chat/groups/image.jpg",
        "status": "active",
        "created_by": {
            "id": 1,
            "first_name": "Jane",
            "last_name": "Smith"
        },
        "members_count": 5,
        "members": [
            {
                "id": 1,
                "user": {
                    "id": 1,
                    "first_name": "Jane",
                    "last_name": "Smith"
                },
                "role": "admin",
                "joined_at": "2026-01-13T18:00:00.000000Z"
            }
        ],
        "created_at": "2026-01-13T18:00:00.000000Z",
        "updated_at": "2026-01-13T18:30:00.000000Z"
    }
}
```

---

### 5. Update Group

**Endpoint**: `PUT /api/chat/groups/{groupId}` or `POST /api/chat/groups/{groupId}` with `_method=PUT`

**Description**: Update group details (Only admin can update)

**Request Body** (multipart/form-data):
```
name: "Updated Group Name" (optional)
description: "Updated description" (optional)
group_image: [image file] (optional)
```

**Postman Instructions**:
1. Use POST method (since PUT doesn't support multipart easily)
2. Add field: `_method` with value `PUT`
3. Add other fields as needed

**Example Response**:
```json
{
    "success": true,
    "message": "Group updated successfully",
    "data": {
        "group": {...},
        "group_image_url": "http://localhost:8000/storage/chat/groups/updated.jpg"
    }
}
```

---

### 6. Delete Group

**Endpoint**: `DELETE /api/chat/groups/{groupId}`

**Description**: Delete a group (Only creator can delete)

**Example Request**:
```
DELETE /api/chat/groups/1
```

**Example Response**:
```json
{
    "success": true,
    "message": "Group deleted successfully"
}
```

---

### 7. Add Members to Group

**Endpoint**: `POST /api/chat/groups/{groupId}/add-members`

**Description**: Add new members to an existing group (Only admin can add)

**Request Body** (JSON):
```json
{
    "member_ids": [5, 6, 7]
}
```

**Example Response**:
```json
{
    "success": true,
    "message": "Members processed",
    "data": {
        "added_members": [
            {
                "id": 5,
                "first_name": "Alice",
                "last_name": "Johnson"
            }
        ],
        "already_members": [6]
    }
}
```

---

### 8. Remove Member from Group

**Endpoint**: `DELETE /api/chat/groups/{groupId}/remove-member/{memberId}`

**Description**: Remove a member from group (Only admin can remove)

**Example Request**:
```
DELETE /api/chat/groups/1/remove-member/5
```

**Example Response**:
```json
{
    "success": true,
    "message": "Member removed successfully"
}
```

---

### 9. Leave Group

**Endpoint**: `POST /api/chat/groups/{groupId}/leave`

**Description**: Leave a group (Creator cannot leave, must delete group)

**Example Request**:
```
POST /api/chat/groups/1/leave
```

**Example Response**:
```json
{
    "success": true,
    "message": "Left group successfully"
}
```

---

### 10. Send Message

**Endpoint**: `POST /api/chat/groups/{groupId}/messages`

**Description**: Send a message (text, image, or voice note)

**Request Body** (multipart/form-data):

**For Text Message**:
```
message_type: "text"
message: "Hello everyone!"
```

**For Image Message**:
```
message_type: "image"
message: "Check this out" (optional)
file: [image file]
```

**For Voice Note**:
```
message_type: "voice"
file: [audio file]
```

**Example Postman Request (Text)**:
1. POST to `http://localhost:8000/api/chat/groups/1/messages`
2. Body → form-data
3. Add fields:
   - message_type: text
   - message: Hello everyone!

**Example Postman Request (Image)**:
1. POST to `http://localhost:8000/api/chat/groups/1/messages`
2. Body → form-data
3. Add fields:
   - message_type: image
   - message: Check this image (optional)
   - file: [select image file]

**Example Response**:
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
            "message": "Hello everyone!",
            "file_path": null,
            "file_name": null,
            "file_size": null,
            "mime_type": null,
            "is_read": false,
            "created_at": "2026-01-13T18:30:00.000000Z",
            "updated_at": "2026-01-13T18:30:00.000000Z",
            "user": {
                "id": 1,
                "first_name": "Jane",
                "last_name": "Smith"
            }
        },
        "file_url": null
    }
}
```

---

### 11. Get Messages

**Endpoint**: `GET /api/chat/groups/{groupId}/messages`

**Description**: Get all messages for a group (paginated)

**Query Parameters**:
- `per_page` (optional, default: 50): Number of messages per page
- `page` (optional, default: 1): Page number

**Example Request**:
```
GET /api/chat/groups/1/messages?per_page=20&page=1
```

**Example Response**:
```json
{
    "success": true,
    "message": "Messages retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "group_id": 1,
                "user_id": 1,
                "message_type": "text",
                "message": "Hello!",
                "file_path": null,
                "file_url": null,
                "is_read": false,
                "created_at": "2026-01-13T18:30:00.000000Z",
                "user": {
                    "id": 1,
                    "first_name": "Jane"
                }
            }
        ],
        "per_page": 20,
        "total": 100
    }
}
```

---

### 12. Delete Message

**Endpoint**: `DELETE /api/chat/messages/{messageId}`

**Description**: Delete a message (Only sender or group admin can delete)

**Example Request**:
```
DELETE /api/chat/messages/1
```

**Example Response**:
```json
{
    "success": true,
    "message": "Message deleted successfully"
}
```

---

### 13. Mark Messages as Read

**Endpoint**: `POST /api/chat/groups/{groupId}/mark-read`

**Description**: Mark all unread messages in a group as read

**Example Request**:
```
POST /api/chat/groups/1/mark-read
```

**Example Response**:
```json
{
    "success": true,
    "message": "Messages marked as read"
}
```

---

## Socket.IO Events

### Client → Server Events

#### 1. user:join
Join the socket server and go online

**Emit**:
```javascript
socket.emit('user:join', {
    userId: 1,
    userName: 'Jane Smith'
});
```

**Response**: Broadcast to all clients
```javascript
socket.on('users:online', (users) => {
    console.log('Online users:', users);
    // users = [{socketId, userId, userName}, ...]
});
```

---

#### 2. group:join
Join a specific group room

**Emit**:
```javascript
socket.emit('group:join', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith'
});
```

**Response**: Broadcast to other group members
```javascript
socket.on('group:user-joined', (data) => {
    console.log(data.message); // "Jane Smith joined the group"
});
```

---

#### 3. group:leave
Leave a group room

**Emit**:
```javascript
socket.emit('group:leave', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith'
});
```

---

#### 4. message:send
Send a message via Socket.IO (real-time)

**Emit**:
```javascript
// Text message
socket.emit('message:send', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith',
    messageType: 'text',
    message: 'Hello everyone!'
});

// Image/Voice message (send file URL from API response)
socket.emit('message:send', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith',
    messageType: 'image',
    message: 'Check this out',
    fileUrl: 'http://localhost:8000/storage/chat/images/image.jpg',
    fileName: 'photo.jpg'
});
```

**Response**: Broadcast to all group members
```javascript
socket.on('message:received', (messageData) => {
    console.log('New message:', messageData);
    // Display message in UI
});
```

---

#### 5. typing:start
Notify others that user is typing

**Emit**:
```javascript
socket.emit('typing:start', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith'
});
```

**Response**:
```javascript
socket.on('typing:user', (data) => {
    if (data.isTyping) {
        console.log(`${data.userName} is typing...`);
    }
});
```

---

#### 6. typing:stop
Notify that user stopped typing

**Emit**:
```javascript
socket.emit('typing:stop', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith'
});
```

---

#### 7. message:read
Mark message as read

**Emit**:
```javascript
socket.emit('message:read', {
    messageId: 123,
    groupId: 1,
    userId: 1
});
```

**Response**:
```javascript
socket.on('message:status', (data) => {
    if (data.status === 'read') {
        // Update UI to show message as read
    }
});
```

---

### Server → Client Events

These events are emitted by the server to clients:

1. **users:online** - List of online users
2. **group:user-joined** - User joined a group
3. **group:user-left** - User left a group
4. **message:received** - New message received
5. **typing:user** - User typing status
6. **message:status** - Message delivery/read status
7. **message:deleted** - Message was deleted
8. **group:info-updated** - Group info updated
9. **group:new-member** - New member added
10. **group:member-left** - Member removed

---

## Complete Postman Testing Flow

### Setup
1. First, login and get access token
2. Add token to Authorization header for all requests

### Test Sequence

1. **Get Lawyers List**
   ```
   GET /api/chat/lawyers
   ```

2. **Create Group**
   ```
   POST /api/chat/groups/create
   Body (form-data):
   - name: "Test Group"
   - description: "Testing"
   - member_ids[0]: 2
   - member_ids[1]: 3
   ```

3. **Get All Groups**
   ```
   GET /api/chat/groups
   ```

4. **Get Group Details**
   ```
   GET /api/chat/groups/1
   ```

5. **Send Text Message**
   ```
   POST /api/chat/groups/1/messages
   Body (form-data):
   - message_type: text
   - message: "Hello team!"
   ```

6. **Send Image Message**
   ```
   POST /api/chat/groups/1/messages
   Body (form-data):
   - message_type: image
   - message: "Check this"
   - file: [select image]
   ```

7. **Send Voice Message**
   ```
   POST /api/chat/groups/1/messages
   Body (form-data):
   - message_type: voice
   - file: [select audio file]
   ```

8. **Get Messages**
   ```
   GET /api/chat/groups/1/messages?per_page=20
   ```

9. **Add Members**
   ```
   POST /api/chat/groups/1/add-members
   Body (JSON):
   {
       "member_ids": [4, 5]
   }
   ```

10. **Update Group**
    ```
    POST /api/chat/groups/1
    Body (form-data):
    - _method: PUT
    - name: "Updated Name"
    ```

---

## Socket.IO Testing with Postman or Browser

### Using Browser Console

```javascript
// Connect to Socket.IO server
const socket = io('http://localhost:3000');

// Join as user
socket.emit('user:join', {
    userId: 1,
    userName: 'Jane Smith'
});

// Join group
socket.emit('group:join', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith'
});

// Send message
socket.emit('message:send', {
    groupId: 1,
    userId: 1,
    userName: 'Jane Smith',
    messageType: 'text',
    message: 'Hello from Socket.IO!'
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

---

## Error Responses

All errors follow this format:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Error message"]
    }
}
```

Common HTTP Status Codes:
- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
- 500: Server Error

---

## File Size Limits

- Images: Max 2MB (group images)
- Messages files (images/voice): Max 10MB

---

## Notes

1. Only users with role `lawyer` can create and participate in groups
2. Group creator is automatically set as admin
3. Only admins can add/remove members and update group details
4. Only group creator can delete the group
5. Messages are stored in database and also broadcast via Socket.IO
6. Files are stored in `storage/app/public/chat/`
7. Run `php artisan storage:link` to make files accessible via URL

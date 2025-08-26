# API Dokumentasi Notifikasi FCM

Dokumentasi ini menjelaskan cara menggunakan API notifikasi untuk mobile app developer.

## Base URL
```
https://your-domain.com/api
```

## Authentication
Semua endpoint memerlukan authentication menggunakan Bearer Token:
```
Authorization: Bearer {your_auth_token}
```

## Endpoints

### 1. Update FCM Token

**POST** `/notifications/fcm-token`

Update FCM token untuk user yang sedang login.

**Request Body:**
```json
{
    "fcm_token": "fMEP0vJqS6:APA91bHqX..."
}
```

**Response:**
```json
{
    "success": true,
    "message": "FCM token updated successfully"
}
```

**Contoh cURL:**
```bash
curl -X POST https://your-domain.com/api/notifications/fcm-token \
  -H "Authorization: Bearer your_auth_token" \
  -H "Content-Type: application/json" \
  -d '{
    "fcm_token": "fMEP0vJqS6:APA91bHqX..."
  }'
```

### 2. Get Notifications

**GET** `/notifications`

Mendapatkan daftar notifikasi untuk user yang sedang login.

**Query Parameters:**
- `status` (optional): Filter by status (`pending`, `sent`, `failed`, `scheduled`)
- `type` (optional): Filter by type (`general`, `presence`, `permit`, `salary`, `announcement`, `system`)
- `read` (optional): Filter by read status (`true`, `false`)
- `per_page` (optional): Number of items per page (default: 20)

**Response:**
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "title": "Check-in Berhasil",
                "body": "Anda telah berhasil check-in pada 08:30",
                "type": "presence",
                "data": {
                    "action": "view_presence",
                    "presence_id": 123,
                    "date": "2025-08-26"
                },
                "status": "sent",
                "priority": "normal",
                "read_at": null,
                "sent_at": "2025-08-26T08:30:00.000000Z",
                "created_at": "2025-08-26T08:30:00.000000Z"
            }
        ],
        "total": 1,
        "per_page": 20
    },
    "unread_count": 5
}
```

### 3. Get Single Notification

**GET** `/notifications/{id}`

Mendapatkan detail notifikasi berdasarkan ID.

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Check-in Berhasil",
        "body": "Anda telah berhasil check-in pada 08:30",
        "type": "presence",
        "data": {
            "action": "view_presence",
            "presence_id": 123,
            "date": "2025-08-26"
        },
        "status": "sent",
        "priority": "normal",
        "read_at": null,
        "sent_at": "2025-08-26T08:30:00.000000Z",
        "created_at": "2025-08-26T08:30:00.000000Z"
    }
}
```

### 4. Mark Notification as Read

**PATCH** `/notifications/{id}/read`

Menandai notifikasi sebagai sudah dibaca.

**Response:**
```json
{
    "success": true,
    "message": "Notification marked as read"
}
```

### 5. Mark Multiple Notifications as Read

**PATCH** `/notifications/mark-read`

Menandai multiple notifikasi sebagai sudah dibaca.

**Request Body:**
```json
{
    "notification_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
    "success": true,
    "message": "5 notifications marked as read"
}
```

### 6. Mark All Notifications as Read

**PATCH** `/notifications/mark-all-read`

Menandai semua notifikasi sebagai sudah dibaca.

**Response:**
```json
{
    "success": true,
    "message": "10 notifications marked as read"
}
```

### 7. Get Notification Statistics

**GET** `/notifications/statistics`

Mendapatkan statistik notifikasi untuk user yang sedang login.

**Response:**
```json
{
    "success": true,
    "data": {
        "total": 25,
        "unread": 5,
        "read": 20,
        "by_type": {
            "presence": 10,
            "permit": 5,
            "announcement": 8,
            "general": 2
        }
    }
}
```

### 8. Delete Notification

**DELETE** `/notifications/{id}`

Menghapus notifikasi berdasarkan ID.

**Response:**
```json
{
    "success": true,
    "message": "Notification deleted successfully"
}
```

### 9. Delete Multiple Notifications

**DELETE** `/notifications`

Menghapus multiple notifikasi.

**Request Body:**
```json
{
    "notification_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
    "success": true,
    "message": "5 notifications deleted successfully"
}
```

## Error Responses

### 400 Bad Request
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "fcm_token": ["The fcm token field is required."]
    }
}
```

### 401 Unauthorized
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```

### 404 Not Found
```json
{
    "success": false,
    "message": "Notification not found"
}
```

### 422 Unprocessable Entity
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "notification_ids": ["The notification ids field is required."]
    }
}
```

## Notification Types

- `general`: Notifikasi umum
- `presence`: Notifikasi terkait kehadiran (check-in/check-out)
- `permit`: Notifikasi terkait izin
- `salary`: Notifikasi terkait gaji
- `announcement`: Notifikasi pengumuman
- `system`: Notifikasi sistem

## Notification Priorities

- `low`: Prioritas rendah
- `normal`: Prioritas normal
- `high`: Prioritas tinggi
- `urgent`: Prioritas sangat tinggi

## Notification Data Structure

Setiap notifikasi memiliki field `data` yang berisi informasi tambahan:

```json
{
    "action": "view_presence",        // Action yang akan dilakukan saat notifikasi di-tap
    "presence_id": 123,              // ID data terkait
    "date": "2025-08-26",            // Tanggal terkait
    "timestamp": "2025-08-26T08:30:00.000000Z"  // Timestamp
}
```

## Implementation Guide

### 1. Update FCM Token saat Login

```javascript
// Setelah user login berhasil
const fcmToken = await messaging().getToken();
await updateFcmToken(fcmToken);

async function updateFcmToken(token) {
    const response = await fetch('/api/notifications/fcm-token', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ fcm_token: token }),
    });
    
    const result = await response.json();
    if (!result.success) {
        console.error('Failed to update FCM token:', result.message);
    }
}
```

### 2. Handle FCM Token Refresh

```javascript
// Listen for token refresh
messaging().onTokenRefresh(() => {
    messaging().getToken().then((refreshedToken) => {
        updateFcmToken(refreshedToken);
    });
});
```

### 3. Get Notifications

```javascript
async function getNotifications(page = 1) {
    const response = await fetch(`/api/notifications?page=${page}`, {
        headers: {
            'Authorization': `Bearer ${authToken}`,
        },
    });
    
    const result = await response.json();
    if (result.success) {
        return result.data;
    }
    
    throw new Error(result.message);
}
```

### 4. Mark Notification as Read

```javascript
async function markAsRead(notificationId) {
    const response = await fetch(`/api/notifications/${notificationId}/read`, {
        method: 'PATCH',
        headers: {
            'Authorization': `Bearer ${authToken}`,
        },
    });
    
    const result = await response.json();
    return result.success;
}
```

### 5. Handle Notification Tap

```javascript
// Handle notification tap when app is in background
messaging().onNotificationOpenedApp((remoteMessage) => {
    const data = remoteMessage.data;
    
    switch (data.action) {
        case 'view_presence':
            navigateToPresenceDetail(data.presence_id);
            break;
        case 'view_permit':
            navigateToPermitDetail(data.permit_id);
            break;
        case 'view_announcement':
            navigateToAnnouncement(data.announcement_id);
            break;
        default:
            navigateToNotifications();
    }
});
```

## Testing

### Test dengan Postman

1. **Update FCM Token:**
   ```
   POST /api/notifications/fcm-token
   Authorization: Bearer your_token
   Content-Type: application/json
   
   {
       "fcm_token": "test_fcm_token_123"
   }
   ```

2. **Get Notifications:**
   ```
   GET /api/notifications
   Authorization: Bearer your_token
   ```

3. **Mark as Read:**
   ```
   PATCH /api/notifications/1/read
   Authorization: Bearer your_token
   ```

### Test dengan cURL

```bash
# Update FCM token
curl -X POST https://your-domain.com/api/notifications/fcm-token \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"fcm_token": "test_token_123"}'

# Get notifications
curl -X GET https://your-domain.com/api/notifications \
  -H "Authorization: Bearer your_token"

# Mark as read
curl -X PATCH https://your-domain.com/api/notifications/1/read \
  -H "Authorization: Bearer your_token"
```

## Notes

1. **FCM Token**: Harus diupdate setiap kali user login atau token refresh
2. **Authentication**: Semua endpoint memerlukan valid authentication
3. **Rate Limiting**: API memiliki rate limiting untuk mencegah abuse
4. **Error Handling**: Selalu handle error responses dengan baik
5. **Offline Support**: Simpan notifikasi lokal untuk offline access

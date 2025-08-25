# API Notifikasi Mobile

Dokumentasi ini menjelaskan cara menggunakan API notifikasi untuk aplikasi mobile.

## Base URL
```
https://your-domain.com/api
```

## Authentication
Semua endpoint memerlukan authentication menggunakan Laravel Sanctum. Include token di header:
```
Authorization: Bearer {your_token}
```

## Endpoints

### 1. Get Notifications
**GET** `/notifications`

Mendapatkan daftar notifikasi untuk user yang sedang login.

**Query Parameters:**
- `status` (optional): Filter berdasarkan status (`pending`, `sent`, `failed`, `scheduled`)
- `type` (optional): Filter berdasarkan tipe (`general`, `presence`, `permit`, `salary`, `announcement`, `system`)
- `read` (optional): Filter berdasarkan status baca (`true` untuk sudah dibaca, `false` untuk belum dibaca)
- `per_page` (optional): Jumlah item per halaman (default: 20)

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
                "body": "Anda telah berhasil check-in pada 08:00",
                "type": "presence",
                "priority": "normal",
                "status": "sent",
                "data": {
                    "presence_id": 123,
                    "action": "view_presence",
                    "date": "2024-01-15"
                },
                "image_url": null,
                "action_url": null,
                "sent_at": "2024-01-15T08:00:00.000000Z",
                "read_at": null,
                "created_at": "2024-01-15T08:00:00.000000Z"
            }
        ],
        "total": 50,
        "per_page": 20
    },
    "unread_count": 5
}
```

### 2. Get Single Notification
**GET** `/notifications/{id}`

Mendapatkan detail notifikasi berdasarkan ID.

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Check-in Berhasil",
        "body": "Anda telah berhasil check-in pada 08:00",
        "type": "presence",
        "priority": "normal",
        "status": "sent",
        "data": {
            "presence_id": 123,
            "action": "view_presence",
            "date": "2024-01-15"
        },
        "image_url": null,
        "action_url": null,
        "sent_at": "2024-01-15T08:00:00.000000Z",
        "read_at": null,
        "created_at": "2024-01-15T08:00:00.000000Z"
    }
}
```

### 3. Mark Notification as Read
**PATCH** `/notifications/{id}/read`

Menandai notifikasi sebagai sudah dibaca.

**Response:**
```json
{
    "success": true,
    "message": "Notification marked as read"
}
```

### 4. Mark Multiple Notifications as Read
**PATCH** `/notifications/mark-read`

Menandai beberapa notifikasi sebagai sudah dibaca.

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

### 5. Mark All Notifications as Read
**PATCH** `/notifications/mark-all-read`

Menandai semua notifikasi sebagai sudah dibaca.

**Response:**
```json
{
    "success": true,
    "message": "10 notifications marked as read"
}
```

### 6. Update FCM Token
**POST** `/notifications/fcm-token`

Memperbarui FCM token untuk push notification.

**Request Body:**
```json
{
    "fcm_token": "your_fcm_token_here"
}
```

**Response:**
```json
{
    "success": true,
    "message": "FCM token updated successfully"
}
```

### 7. Get Notification Statistics
**GET** `/notifications/statistics`

Mendapatkan statistik notifikasi.

**Response:**
```json
{
    "success": true,
    "data": {
        "total": 50,
        "unread": 5,
        "read": 45,
        "by_type": {
            "presence": 20,
            "permit": 15,
            "general": 10,
            "announcement": 5
        }
    }
}
```

### 8. Delete Notification
**DELETE** `/notifications/{id}`

Menghapus notifikasi.

**Response:**
```json
{
    "success": true,
    "message": "Notification deleted successfully"
}
```

### 9. Delete Multiple Notifications
**DELETE** `/notifications`

Menghapus beberapa notifikasi.

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

### 404 Not Found
```json
{
    "success": false,
    "message": "Notification not found"
}
```

### 422 Validation Error
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
    "message": "Unauthenticated."
}
```

## Notification Types

- `general`: Notifikasi umum
- `presence`: Notifikasi terkait kehadiran (check-in, check-out, keterlambatan)
- `permit`: Notifikasi terkait pengajuan izin
- `salary`: Notifikasi terkait gaji
- `announcement`: Notifikasi pengumuman
- `system`: Notifikasi sistem

## Notification Priorities

- `low`: Prioritas rendah
- `normal`: Prioritas normal
- `high`: Prioritas tinggi
- `urgent`: Prioritas sangat tinggi

## Notification Status

- `pending`: Menunggu pengiriman
- `sent`: Berhasil dikirim
- `failed`: Gagal dikirim
- `scheduled`: Terjadwal untuk dikirim

## Data Structure

Field `data` berisi informasi tambahan yang dapat digunakan oleh aplikasi mobile:

### Presence Notification
```json
{
    "presence_id": 123,
    "action": "view_presence",
    "date": "2024-01-15",
    "late_duration": 15
}
```

### Permit Notification
```json
{
    "permit_id": 456,
    "action": "view_permit",
    "start_date": "2024-01-20",
    "end_date": "2024-01-22",
    "type": "sick",
    "status": "approved"
}
```

## Implementation Example (Flutter)

```dart
class NotificationService {
  final String baseUrl = 'https://your-domain.com/api';
  final String token = 'your_auth_token';

  Future<List<Notification>> getNotifications({
    String? status,
    String? type,
    bool? read,
    int perPage = 20,
  }) async {
    final queryParams = <String, String>{};
    if (status != null) queryParams['status'] = status;
    if (type != null) queryParams['type'] = type;
    if (read != null) queryParams['read'] = read.toString();
    queryParams['per_page'] = perPage.toString();

    final response = await http.get(
      Uri.parse('$baseUrl/notifications').replace(queryParameters: queryParams),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return (data['data']['data'] as List)
          .map((json) => Notification.fromJson(json))
          .toList();
    } else {
      throw Exception('Failed to load notifications');
    }
  }

  Future<void> markAsRead(int notificationId) async {
    final response = await http.patch(
      Uri.parse('$baseUrl/notifications/$notificationId/read'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );

    if (response.statusCode != 200) {
      throw Exception('Failed to mark notification as read');
    }
  }

  Future<void> updateFcmToken(String fcmToken) async {
    final response = await http.post(
      Uri.parse('$baseUrl/notifications/fcm-token'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
      body: json.encode({
        'fcm_token': fcmToken,
      }),
    );

    if (response.statusCode != 200) {
      throw Exception('Failed to update FCM token');
    }
  }
}
```

## Setup FCM di Mobile App

1. **Android**: Tambahkan `google-services.json` ke project
2. **iOS**: Tambahkan `GoogleService-Info.plist` ke project
3. **Flutter**: Install package `firebase_messaging`
4. **Update FCM Token**: Panggil endpoint `/notifications/fcm-token` setiap kali token berubah

## Testing

Untuk testing, Anda dapat menggunakan Postman atau curl:

```bash
# Get notifications
curl -X GET "https://your-domain.com/api/notifications" \
  -H "Authorization: Bearer your_token"

# Mark as read
curl -X PATCH "https://your-domain.com/api/notifications/1/read" \
  -H "Authorization: Bearer your_token"

# Update FCM token
curl -X POST "https://your-domain.com/api/notifications/fcm-token" \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"fcm_token": "test_token"}'
```

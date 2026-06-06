# SSEMOD - Server-Sent Events Chat Extension

A robust real-time chat system extension with country flag transparency, secure file attachments, and Server-Sent Events (SSE) streaming. Built with **PHP**, **JavaScript**, and **CSS**.

## 🌟 Features

### Real-Time Messaging with SSE
- **Direct SSE Stream Connections**: Establishes persistent server-sent event streams via `/stream/direct/{room_id}` for instant message delivery
- **Fallback Polling**: Automatic fallback to HTTP polling for environments with SSE limitations
- **Route Parameter Resolution**: Intelligently resolves room IDs from URL parameters with query string fallback

### 🌍 Country Flag Transparency
- **IP Geolocation Mapping**: Integrates with `ip-api.com` to detect visitor locations with 1.5s timeout
- **Multi-User Support**: 
  - Guest users: Country flags stored in FileStore session JSON
  - Registered users: Flags persisted in MySQL `users.country_flag` column
- **UI Display**: Country flag emojis displayed next to user gender icons in the online users list and direct chat headers

### 📎 File Attachments (Up to 25MB)
- **Secure Upload Handling**: Multipart file uploads with strict 25MB server-side validation
- **Smart File Type Detection**: Automatic classification as image, video, audio, or generic file
- **Secure File Serving**: Download endpoint with proper MIME type headers and access control
- **Rich Media Rendering**:
  - **Images**: Inline display with thumbnails
  - **Videos**: HTML5 video player with controls
  - **Audio**: HTML5 audio player with playback controls
  - **Files**: Clickable download links with file size information
- **Client-Side Preview**: Live attachment preview bar before sending with cancel options

### 🔧 URL Resolution & Navigation
- **Trailing-Slash Handling**: Automatic redirect for consistent path resolution
- **Global BASE_URL Injection**: Centralized base URL configuration in all views
- **Fetch Interceptor**: Automatic URL prefix injection for all fetch requests
- **Keyboard Integration**: Enter key support for instant message sending

## 📋 Technical Stack

- **Backend**: PHP with custom routing and SSE implementation
- **Database**: MySQL with schema extensions for attachments
- **Frontend**: Vanilla JavaScript with EventSource API
- **Storage**: Local file system with secure attachment directory (`/storage/attachments/`)

## 🚀 Getting Started

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Web server (Apache/Nginx with mod_rewrite)
- 25MB+ available disk space for attachments

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yaratul2005/SSEMOD.git
   cd SSEMOD
   ```

2. **Install dependencies** (if using Composer)
   ```bash
   composer install
   ```

3. **Database Setup**
   ```sql
   -- Add country flag column to users table
   ALTER TABLE users ADD COLUMN country_flag VARCHAR(10) DEFAULT '🇺🇸';
   
   -- Add attachment columns to messages table
   ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255);
   ALTER TABLE messages ADD COLUMN attachment_type ENUM('image', 'video', 'audio', 'file');
   ```

4. **Create attachment storage directory**
   ```bash
   mkdir -p storage/attachments
   chmod 755 storage/attachments
   ```

5. **Configure base URL** in `public/index.php`
   ```php
   define('BASE_URL', 'http://localhost/socialcc/public/');
   ```

## 📖 Usage Guide

### For End Users

#### Viewing Country Flags
1. Log in to your account (guest or registered)
2. Check the left panel user list for country flag emojis next to gender icons
3. Open a direct message chat and view the stranger's flag in the header

#### Sending Messages
1. Type your message in the chat input
2. Press **Enter** or click the **Send** button
3. Message appears instantly via SSE stream

#### Uploading File Attachments
1. Click the **📎 Paperclip icon** in the message footer
2. Select a file (max 25MB)
3. Live preview shows:
   - Image thumbnails for images
   - Video icons for videos
   - File icons for other types
4. Click **Send** to upload
5. File renders inline in the chat bubble with appropriate player/download link

### For Developers

#### SSE Stream Endpoint
```javascript
// Establish direct SSE connection
const roomId = '12345';
const eventSource = new EventSource(`/stream/direct/${roomId}`);

eventSource.addEventListener('message', (event) => {
  const message = JSON.parse(event.data);
  renderMessage(message);
});
```

#### File Upload via API
```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('room_id', roomId);

const response = await fetch(`${BASE_URL}api/message/send`, {
  method: 'POST',
  body: formData
});
```

#### Geolocation Helper
```php
use IpGeolocation;

$ip = $_SERVER['REMOTE_ADDR'];
$location = IpGeolocation::getCountryFlag($ip); // Returns emoji flag
```

## 🧪 Testing & Verification

### Automated Test Suite
Run the comprehensive extensions test:
```bash
php scratch/test_extensions.php
```

This verifies:
- Geolocation resolution and fallback handling
- File attachment database operations
- SSE stream path parameter resolution

### Manual Testing Checklist

1. **Basic Navigation**
   - [ ] Visit `http://localhost/socialcc/public/` with and without trailing slash
   - [ ] Both paths work without redirect loops

2. **Country Flags**
   - [ ] Login as guest or registered user
   - [ ] Your flag displays in the online users list
   - [ ] Open a direct chat
   - [ ] Stranger's flag displays in the chat header
   - [ ] Flag persists after page reload

3. **Real-Time Messaging**
   - [ ] Type a message and press Enter
   - [ ] Message sends immediately (SSE stream active)
   - [ ] Click Send button as alternative
   - [ ] Messages render in correct order
   - [ ] No message duplication

4. **File Attachments**
   - [ ] Click paperclip icon to open file dialog
   - [ ] Image file: Preview thumbnail, send, renders inline
   - [ ] Video file: Preview icon, send, displays video player
   - [ ] Audio file: Preview icon, send, displays audio player
   - [ ] Generic file (PDF/ZIP): Preview icon, send, clickable download link
   - [ ] File > 25MB: Alert blocks upload
   - [ ] Download attachment: Correct file type and integrity

## 📁 Project Structure

```
SSEMOD/
├── public/
│   ├── index.php                 # Entry point with BASE_URL injection
│   ├── js/
│   │   └── sse-engine.js        # SSE stream and polling engine
│   └── css/
│       └── styles.css            # Attachment preview styling
├── app/
│   ├── Controllers/
│   │   ├── StreamController.php  # SSE endpoint handler
│   │   ├── ChatController.php    # Message send/receive
│   │   └── ProfileController.php # File serving endpoint
│   └── Helpers/
│       └── IpGeolocation.php     # IP-to-flag mapping
├── storage/
│   └── attachments/              # Uploaded files directory
├── scratch/
│   └── test_extensions.php       # Test suite
└── README.md
```

## 🔐 Security Features

- **File Upload Validation**: 25MB limit enforced server-side and client-side
- **Unique Filenames**: Prevents overwrite attacks
- **MIME Type Detection**: Correct headers set for file serving
- **Path Traversal Protection**: Attachment serving uses safe filename validation
- **Session Security**: Country flags associated with authenticated sessions
- **Rate Limiting**: (Configurable) Per-user message frequency limits

## 🐛 Known Issues & Fixes

### Critical Fixes Applied
1. **SSE Route Parameter Resolution**: Fixed `/stream/direct/{room_id}` 400 errors by resolving route parameters instead of relying on `$_GET`
2. **URL Trailing Slash**: Resolved inconsistent path navigation with automatic redirect
3. **Relative Path Resolution**: Global `BASE_URL` injection ensures all fetches work across different deployment paths
4. **Keyboard Enter Handler**: Enter key now properly triggers message send

## 📊 Performance Metrics

- **SSE Connection Timeout**: 1.5s for geolocation API
- **Polling Interval** (fallback): 2s between checks
- **Max File Size**: 25MB per attachment
- **DB Query Optimization**: Indexed `room_id` and `user_id` in messages table

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:
1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make your changes and test thoroughly
3. Submit a pull request with detailed description
4. Run the test suite: `php scratch/test_extensions.php`

## 📝 License

This project is part of the ChatArena application ecosystem.

## 📞 Support

For issues, questions, or feature requests, please:
1. Check the manual testing guide above
2. Run the automated test suite to verify environment
3. Review the inline code documentation
4. Open an issue on GitHub with detailed reproduction steps

## 🎯 Roadmap

- [ ] WebSocket support for even lower latency
- [ ] File compression for video attachments
- [ ] Message encryption end-to-end
- [ ] Typing indicators
- [ ] Message read receipts
- [ ] Voice message recording
- [ ] Emoji support in messages

---

**Last Updated**: June 2026  
**Version**: 1.0.0  
**Status**: Production Ready

Built with ❤️ by the ChatArena development team

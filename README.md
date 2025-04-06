# 🎬 Castify (Netflix Clone API)

A robust **backend API** for a **Netflix-like streaming platform** built with **Laravel**. This API provides essential features such as **user management**, **video streaming**, **recommendations**, and **analytics**. Perfect for scaling your video streaming platform with advanced media processing.

---

## 📦 Features

- **User Management**: 
  - 🔑 Registration, authentication, and multiple user profiles
- **Video Management**: 
  - 🎥 Upload, processing, and playback of video content
- **Advanced Media Processing**: 
  - ⚡ Adaptive bitrate streaming, DRM protection, and subtitle generation
- **Recommendation Engine**: 
  - 🤖 Machine learning-based content suggestions
- **Analytics**: 
  - 📊 Track viewing patterns and content performance

---

## 🔧 Tech Stack

- **Framework**: Laravel 10
- **Database**: PostgreSQL (via Render)
- **Queue**: Redis (via Render)
- **Media Storage & Processing**: Cloudinary
- **Email Service**: Resend
- **Error Tracking**: Sentry
- **API Documentation**: Swagger via L5-Swagger

---

## 🚀 Deployment on Render

This application is configured for deployment on **Render** with the following components:

1. **Web Service**: 
   - 🌐 Runs the main Laravel application
2. **Worker**: 
   - 🛠️ Processes background jobs (video transcoding, subtitle generation)
3. **Scheduler**: 
   - ⏰ Runs scheduled tasks for maintenance and reporting
4. **PostgreSQL**: 
   - 💾 Database for persistent storage
5. **Redis**: 
   - 🔄 For queue processing and caching

---

### 🔨 **Deployment Steps**

1. Fork or clone this repository
2. Connect your **GitHub repository** to **Render**
3. Sign up for the required third-party services:
   - **Cloudinary**: For video storage and processing 🎞️
   - **Resend**: For transactional emails 📧
   - **Sentry**: For error tracking (optional) 🐞
4. Deploy the service on Render

Render will automatically handle:
- Installing **FFmpeg** and dependencies
- Setting up the **database**
- Running **migrations**
- Configuring the **queue worker** and **scheduler**

For detailed deployment instructions, check the [Render Deployment Guide](docs/deployment/render-guide.md).

---

## 🖥️ **Local Development Setup**

### 🛠️ Prerequisites

- PHP 8.1+
- Composer 📦
- PostgreSQL or MySQL 🗄️
- FFmpeg (for local video processing) 🎞️
- Cloudinary account (for video storage) 🏞️

### 🔧 Installation Steps

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/netflix-clone.git
   cd netflix-clone
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

5. Start the server:
   ```bash
   php artisan serve
   ```

---

## 📚 **API Documentation**

API documentation is generated using **Swagger**. After installation, you can access it at:

```
http://localhost:8000/api/documentation
```

---

## 📄 **License**

This project is open-sourced software licensed under the [MIT license](LICENSE).

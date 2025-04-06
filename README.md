# Netflix Clone API

A robust backend API for a Netflix-like streaming platform built with Laravel. This API provides features for user management, video streaming, recommendations, and analytics.

## Features

- **User Management**: Registration, authentication, and multiple user profiles
- **Video Management**: Upload, processing, and playback of video content
- **Advanced Media Processing**: Adaptive bitrate streaming, DRM protection, and subtitle generation
- **Recommendation Engine**: Machine learning-based content suggestions
- **Analytics**: Track viewing patterns and content performance

## Tech Stack

- **Framework**: Laravel 10
- **Database**: PostgreSQL (via Render)
- **Queue**: Redis (via Render)
- **Media Storage & Processing**: Cloudinary
- **Email Service**: Resend
- **Error Tracking**: Sentry
- **API Documentation**: Swagger via L5-Swagger

## Deployment on Render

This application is configured for deployment on Render with the following components:

1. **Web Service**: Runs the main Laravel application
2. **Worker**: Processes background jobs (video transcoding, subtitle generation)
3. **Scheduler**: Runs scheduled tasks for maintenance and reporting
4. **PostgreSQL**: Database for persistent storage
5. **Redis**: For queue processing and caching

### Deployment Steps

1. Fork or clone this repository
2. Connect your GitHub repository to Render
3. Sign up for required third-party services:
   - **Cloudinary**: For video storage and processing
   - **Resend**: For transactional emails
   - **Sentry**: For error tracking (optional)
4. Add the environment variables in the Render dashboard:
   - Cloudinary credentials (CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET)
   - Resend API key (RESEND_API_KEY)
   - Sentry DSN (SENTRY_LARAVEL_DSN) (optional)
   - Frontend URL (FRONTEND_URL)
5. Deploy the service

Render will automatically:
- Install FFmpeg and other dependencies
- Set up the database
- Run migrations
- Configure the queue worker and scheduler

For detailed deployment instructions, see the [Render Deployment Guide](docs/deployment/render-guide.md).

## Required Environment Variables

The application requires the following environment variables to be set in the Render dashboard:

### Core Application
- `APP_KEY`: Generated automatically by Render
- `APP_URL`: Set automatically to your Render URL

### Database (Set automatically by Render)
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Redis (Set automatically by Render)
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`

### Cloudinary (Required for video storage)
- `CLOUDINARY_CLOUD_NAME`: Your Cloudinary cloud name
- `CLOUDINARY_API_KEY`: Your Cloudinary API key
- `CLOUDINARY_API_SECRET`: Your Cloudinary API secret
- `CLOUDINARY_UPLOAD_PRESET`: Cloudinary upload preset name (default: "netflix_videos")

### Email (Required for user notifications)
- `MAIL_MAILER`: Set to "resend"
- `MAIL_FROM_ADDRESS`: Your sender email address
- `MAIL_FROM_NAME`: Your sender name
- `RESEND_API_KEY`: Your Resend API key

### Error Tracking (Optional)
- `SENTRY_LARAVEL_DSN`: Your Sentry DSN
- `SENTRY_TRACES_SAMPLE_RATE`: Sampling rate for performance tracking (default: 0.1)

### Frontend Integration
- `FRONTEND_URL`: URL of your Vue.js frontend

## Local Development Setup

### Prerequisites

- PHP 8.1+
- Composer
- PostgreSQL or MySQL
- FFmpeg (for local video processing)
- Redis (optional, for queue processing)
- Cloudinary account (for video storage)

### Installation Steps

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

4. Configure your database and Cloudinary credentials in `.env`

5. Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

6. Start the server:
   ```bash
   php artisan serve
   ```

## API Documentation

API documentation is generated using Swagger. After installation, you can access it at:

```
http://localhost:8000/api/documentation
```

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

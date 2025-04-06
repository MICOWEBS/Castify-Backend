# Render Deployment Guide

This guide provides step-by-step instructions for deploying the Netflix Clone API to Render.

## Prerequisites

- A [Render account](https://render.com/)
- A GitHub repository with your Netflix Clone code
- Basic familiarity with cloud deployment concepts

## Step 1: Prepare Your Repository

Ensure your repository has these files configured properly:
- `render.yaml` - Defines the services, databases, and environment variables
- `render-build.sh` - Build script for installing dependencies and setting up the application
- `.env.example` - Template for environment variables

## Step 2: Connect Your Repository to Render

1. Log in to your [Render Dashboard](https://dashboard.render.com/)
2. Click "New" and select "Blueprint"
3. Connect your GitHub account if you haven't already
4. Select your Netflix Clone repository
5. Render will detect the `render.yaml` file and display the services to be created
6. Click "Apply Blueprint"

## Step 3: Configure Environment Variables

While Render will set up many environment variables automatically based on your `render.yaml`, you'll need to add some manual configurations:

1. Go to your Web Service in the Render dashboard
2. Click on the "Environment" tab
3. Add the following variables:
   - `APP_KEY`: Generate one using `php artisan key:generate --show`
   - `MAIL_*`: Configure your email settings
   - Speech-to-text settings if using subtitle generation
   - DRM settings if using content protection

## Step 4: Set Up Additional Services

### Set Up Persistent Disk for Media Storage

1. Go to your Web Service in the Render dashboard
2. Select "Disks" tab
3. Click "Add Disk"
4. Configure a disk for media storage:
   - Name: `media-storage`
   - Mount Path: `/var/www/storage/app/public`
   - Size: Start with 10GB, adjust as needed

### Configure Security Settings

1. Go to your Web Service
2. Select "Settings" tab
3. Set up appropriate outbound (egress) rules if needed
4. Configure custom domains if you have them

## Step 5: Monitor Deployment

1. Go to "Events" tab to monitor build progress
2. Check logs for any errors

## Step 6: Post-Deployment Steps

After successful deployment:

1. Seed the database (if not done in the build script):
   ```
   php artisan db:seed
   ```

2. Generate Swagger documentation:
   ```
   php artisan l5-swagger:generate
   ```

3. Create the symbolic link for public storage:
   ```
   php artisan storage:link
   ```

## Step 7: Testing Your Deployment

Verify that the following components are working:

1. **API Base Functionality**: Visit your application URL and check the health endpoint
2. **Database Connectivity**: Try logging in with seeded user credentials
3. **Media Processing**: Upload a test video to check FFmpeg functionality
4. **Queue Processing**: Verify that the worker service is processing jobs

## Troubleshooting Common Issues

### FFmpeg Installation Issues

If FFmpeg isn't working:
1. Check the build logs for FFmpeg installation errors
2. Verify the FFmpeg path in your environment variables
3. Try running a manual FFmpeg command via SSH:
   ```
   ffmpeg -version
   ```

### Database Connectivity Issues

If database connection fails:
1. Check the database connection string
2. Verify that the PostgreSQL instance is running
3. Check for firewall or network connectivity issues

### Queue Worker Issues

If background jobs aren't running:
1. Check the worker logs
2. Verify Redis connection settings
3. Ensure the worker is properly configured to process the correct queue

## Scaling Your Application

As your user base grows:

1. **Web Service**: Increase the number of instances or use a larger instance type
2. **Database**: Upgrade to a larger database plan
3. **Redis**: Monitor Redis usage and upgrade if necessary
4. **Media Storage**: Increase disk size or consider moving to a dedicated storage service

## Cost Management

Tips for managing costs on Render:

1. Use the smallest viable instance sizes during development
2. Scale up only when needed based on traffic
3. Monitor disk usage and clean up processed or temporary files
4. Consider suspending services when not in active development

## Additional Resources

- [Render Documentation](https://render.com/docs)
- [Laravel Deployment Best Practices](https://laravel.com/docs/10.x/deployment)
- [FFmpeg Documentation](https://ffmpeg.org/documentation.html) 
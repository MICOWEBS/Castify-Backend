<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'drm' => [
        'enabled' => env('DRM_ENABLED', false),
        'provider' => env('DRM_PROVIDER', 'widevine'), // widevine, playready, fairplay
        'license_server' => env('DRM_LICENSE_SERVER', ''),
        'content_key' => env('DRM_CONTENT_KEY', ''),
        'api_key' => env('DRM_API_KEY', ''),
        'signing_key' => env('DRM_SIGNING_KEY', ''),
        'signing_iv' => env('DRM_SIGNING_IV', ''),
        'certificate' => env('DRM_CERTIFICATE_PATH', ''),
    ],
    
    'speech_to_text' => [
        'enabled' => env('SPEECH_TO_TEXT_ENABLED', false),
        'provider' => env('SPEECH_TO_TEXT_PROVIDER', 'google'), // google, aws, azure
        'api_key' => env('SPEECH_TO_TEXT_API_KEY', ''),
        'additional_languages' => env('SPEECH_TO_TEXT_ADDITIONAL_LANGUAGES', 'es,fr'), // comma-separated list
        
        // Google Cloud Speech-to-Text specific settings
        'google' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', ''),
            'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS', ''),
        ],
        
        // AWS Transcribe specific settings
        'aws' => [
            'access_key' => env('AWS_ACCESS_KEY_ID', ''),
            'secret_key' => env('AWS_SECRET_ACCESS_KEY', ''),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        
        // Azure Speech Service specific settings
        'azure' => [
            'region' => env('AZURE_SPEECH_REGION', 'eastus'),
            'subscription_key' => env('AZURE_SPEECH_KEY', ''),
        ],
    ],
    
    'ffmpeg' => [
        'binary_path' => env('FFMPEG_BINARY_PATH', '/usr/bin/ffmpeg'),
        'ffprobe_path' => env('FFPROBE_BINARY_PATH', '/usr/bin/ffprobe'),
        'timeout' => env('FFMPEG_TIMEOUT', 3600), // 1 hour default timeout
        'threads' => env('FFMPEG_THREADS', 2), // Number of threads to use for processing
    ],
    
    'notifications' => [
        'admin_emails' => explode(',', env('ADMIN_NOTIFICATION_EMAILS', '')),
        'slack_webhook' => env('NOTIFICATION_SLACK_WEBHOOK', ''),
        'error_threshold' => env('ERROR_NOTIFICATION_THRESHOLD', 5), // Number of errors before alerting
        'processing_alert_threshold' => env('PROCESSING_ALERT_THRESHOLD', 30), // Minutes before alerting on long processing
    ],
    
    'queue' => [
        'video_processing' => [
            'max_attempts' => env('QUEUE_VIDEO_MAX_ATTEMPTS', 3),
            'retry_after' => env('QUEUE_VIDEO_RETRY_AFTER', 600), // 10 minutes
            'block_for' => env('QUEUE_VIDEO_BLOCK_FOR', 5), // 5 seconds
            'max_jobs' => env('QUEUE_VIDEO_MAX_JOBS', 5), // Maximum concurrent video processing jobs
        ],
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
        'secure' => true,
    ],

];

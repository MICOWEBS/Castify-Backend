<?php

namespace App\Notifications;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class VideoProcessingFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The video instance.
     *
     * @var \App\Models\Video
     */
    protected $video;

    /**
     * The error message.
     *
     * @var string
     */
    protected $errorMessage;
    
    /**
     * Whether this is an admin notification.
     *
     * @var bool
     */
    protected $isAdminNotification;

    /**
     * Create a new notification instance.
     */
    public function __construct(Video $video, string $errorMessage, bool $isAdminNotification = false)
    {
        $this->video = $video;
        $this->errorMessage = $errorMessage;
        $this->isAdminNotification = $isAdminNotification;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Video Processing Failed: ' . $this->video->title)
            ->greeting('Hello ' . ($notifiable->name ?? ''))
            ->line('We encountered an issue while processing your video:')
            ->line(new HtmlString('<strong>' . $this->video->title . '</strong>'))
            ->line('Error details:')
            ->line(new HtmlString('<pre>' . $this->errorMessage . '</pre>'));
        
        if ($this->isAdminNotification) {
            // Add additional information for admins
            $mailMessage->line('User: ' . $this->video->user->name . ' (' . $this->video->user->email . ')')
                ->line('Video ID: ' . $this->video->id)
                ->line('Uploaded at: ' . $this->video->created_at->toDateTimeString())
                ->action('View Video in Admin Dashboard', url('/admin/videos/' . $this->video->id));
        } else {
            // Regular user notification
            $mailMessage->line('Our team has been notified and will look into this issue.')
                ->line('You can try uploading the video again or contact support if the problem persists.')
                ->action('View Your Videos', url('/dashboard/videos'));
        }
        
        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'video_id' => $this->video->id,
            'video_title' => $this->video->title,
            'error' => $this->errorMessage,
            'user_id' => $this->video->user_id,
            'timestamp' => now()->toDateTimeString(),
            'is_admin_notification' => $this->isAdminNotification,
        ];
    }
}

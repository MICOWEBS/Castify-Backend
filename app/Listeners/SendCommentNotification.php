<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendCommentNotification implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CommentCreated $event): void
    {
        try {
            // Get the video owner
            $video = Video::find($event->comment->video_id);
            $videoOwner = User::find($video->user_id);
            
            // Don't notify if the comment is from the video owner
            if ($event->comment->user_id === $videoOwner->id) {
                return;
            }

            // In a real application, you would:
            // 1. Send an email to the video owner
            // 2. Create a notification in the database
            // 3. Send a push notification if applicable
            
            // For now, let's just log the notification
            Log::info('New comment notification', [
                'video_id' => $event->comment->video_id,
                'video_title' => $video->title,
                'comment_id' => $event->comment->id,
                'from_user' => $event->comment->user->first_name . ' ' . $event->comment->user->last_name,
                'to_user' => $videoOwner->first_name . ' ' . $videoOwner->last_name,
            ]);
            
            /*
            // Example of sending an email notification
            $videoOwner->notify(new NewCommentNotification($event->comment, $video));
            */
        } catch (\Exception $e) {
            Log::error('Error sending comment notification: ' . $e->getMessage());
            $this->fail($e);
        }
    }
}

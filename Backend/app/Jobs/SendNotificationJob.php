<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct($notificationId)
    {
        $this->notificationId = $notificationId;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $notification = Notification::find($this->notificationId);
            
            if (!$notification) {
                Log::warning("Notification not found: {$this->notificationId}");
                return;
            }

            if ($notification->status !== Notification::STATUS_PENDING) {
                Log::info("Notification {$this->notificationId} is not pending, skipping.");
                return;
            }

            // Get recipient
            $recipient = $notification->recipient;
            if (!$recipient) {
                Log::warning("Recipient not found for notification: {$this->notificationId}");
                $notification->markAsFailed();
                return;
            }

            // Send notification
            $success = $notificationService->sendToRecipient(
                $recipient,
                $notification->title,
                $notification->body,
                $notification->data ?? [],
                [
                    'type' => $notification->type,
                    'priority' => $notification->priority,
                    'image_url' => $notification->image_url,
                    'action_url' => $notification->action_url,
                ]
            );

            if ($success) {
                Log::info("Notification {$this->notificationId} sent successfully via job.");
            } else {
                Log::error("Failed to send notification {$this->notificationId} via job.");
            }
        } catch (\Exception $e) {
            Log::error("Exception in SendNotificationJob for notification {$this->notificationId}: " . $e->getMessage());
            
            // Mark notification as failed
            $notification = Notification::find($this->notificationId);
            if ($notification) {
                $notification->markAsFailed();
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotificationJob failed for notification {$this->notificationId}: " . $exception->getMessage());
        
        // Mark notification as failed
        $notification = Notification::find($this->notificationId);
        if ($notification) {
            $notification->markAsFailed();
        }
    }
}

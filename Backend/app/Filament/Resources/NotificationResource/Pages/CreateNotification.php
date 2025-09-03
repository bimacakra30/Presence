<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\Notification as NotificationModel;

class CreateNotification extends CreateRecord
{
    protected static string $resource = NotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Log the incoming data for debugging
        \Illuminate\Support\Facades\Log::info('CreateNotification - Incoming data:', $data);
        
        // Always ensure recipient_id is set for Employee type
        if (isset($data['recipient_type']) && $data['recipient_type'] === Employee::class) {
            // If sending to all employees or recipient_id is not set
            if ((isset($data['send_to_all_employees']) && $data['send_to_all_employees']) || 
                !isset($data['recipient_id']) || empty($data['recipient_id'])) {
                
                // Get first employee as temporary recipient
                $firstEmployee = Employee::whereNotNull('uid')->where('status', 'aktif')->first();
                if ($firstEmployee) {
                    $data['recipient_id'] = $firstEmployee->id;
                    \Illuminate\Support\Facades\Log::info("Set recipient_id to first employee: {$firstEmployee->id}");
                } else {
                    // Fallback to a default value
                    $data['recipient_id'] = 1;
                    \Illuminate\Support\Facades\Log::info("Set recipient_id to fallback: 1");
                }
            }
        }
        
        // Ensure recipient_id is always set for any recipient_type
        if (!isset($data['recipient_id']) || empty($data['recipient_id'])) {
            if (isset($data['recipient_type']) && $data['recipient_type'] === Employee::class) {
                $firstEmployee = Employee::whereNotNull('uid')->where('status', 'aktif')->first();
                $data['recipient_id'] = $firstEmployee ? $firstEmployee->id : 1;
                \Illuminate\Support\Facades\Log::info("Set recipient_id to employee: {$data['recipient_id']}");
            } elseif (isset($data['recipient_type']) && $data['recipient_type'] === User::class) {
                $firstUser = User::first();
                $data['recipient_id'] = $firstUser ? $firstUser->id : 1;
                \Illuminate\Support\Facades\Log::info("Set recipient_id to user: {$data['recipient_id']}");
            } else {
                $data['recipient_id'] = 1; // Default fallback
                \Illuminate\Support\Facades\Log::info("Set recipient_id to default: 1");
            }
        }
        
        // Log the final data
        \Illuminate\Support\Facades\Log::info('CreateNotification - Final data:', $data);
        
        return $data;
    }

    protected function beforeCreate(): void
    {
        // Additional safety check - ensure recipient_id is set
        $data = $this->data;
        
        if (!isset($data['recipient_id']) || empty($data['recipient_id'])) {
            \Illuminate\Support\Facades\Log::warning('CreateNotification - recipient_id still missing in beforeCreate, setting fallback');
            
            if (isset($data['recipient_type']) && $data['recipient_type'] === Employee::class) {
                $firstEmployee = Employee::whereNotNull('uid')->where('status', 'aktif')->first();
                $this->data['recipient_id'] = $firstEmployee ? $firstEmployee->id : 1;
            } else {
                $this->data['recipient_id'] = 1;
            }
        }
    }

    protected function afterCreate(): void
    {
        $notification = $this->record;
        $sendToAllEmployees = request()->input('send_to_all_employees', false);
        
        // Prepare data for FCM
        $data = $notification->data ?? [];
        $data['action'] = 'manual_notification';
        $data['notification_id'] = $notification->id;
        $data['created_via'] = 'filament_admin';

        $options = [
            'type' => $notification->type,
            'priority' => $notification->priority,
            'image_url' => $notification->image_url,
            'action_url' => $notification->action_url,
        ];

        $notificationService = new NotificationService();
        
        if ($sendToAllEmployees && $notification->recipient_type === Employee::class) {
            // Send to all employees
            $this->sendToAllEmployees($notification, $data, $options, $notificationService);
        } else {
            // Send to single recipient
            $this->sendToSingleRecipient($notification, $data, $options, $notificationService);
        }
    }

    protected function sendToAllEmployees($notification, $data, $options, $notificationService): void
    {
        // Use the existing method for sending to all employees
        $results = $notificationService->sendToAllEmployeesWithFirestoreTokens(
            $notification->title,
            $notification->body,
            $data,
            $options
        );

        if (empty($results)) {
            FilamentNotification::make()
                ->title('No Employees Found')
                ->body('No active employees with FCM tokens found to send notifications to.')
                ->warning()
                ->send();
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        // Update notification status based on results
        if ($successCount > 0) {
            $notification->update([
                'status' => NotificationModel::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } else {
            $notification->update([
                'status' => NotificationModel::STATUS_FAILED,
            ]);
        }

        // Show results
        if ($successCount > 0 && $failCount === 0) {
            FilamentNotification::make()
                ->title('Notifications Sent Successfully')
                ->body("Successfully sent to all {$successCount} employees.")
                ->success()
                ->send();
        } elseif ($successCount > 0 && $failCount > 0) {
            FilamentNotification::make()
                ->title('Partial Success')
                ->body("Successfully sent to {$successCount} employees, failed for {$failCount} employees.")
                ->warning()
                ->send();
        } else {
            FilamentNotification::make()
                ->title('Send Failed')
                ->body("Failed to send to all {$failCount} employees. Check the logs for details.")
                ->danger()
                ->send();
        }
    }

    protected function sendToSingleRecipient($notification, $data, $options, $notificationService): void
    {
        // Get recipient
        $recipient = null;
        if ($notification->recipient_type === Employee::class) {
            $recipient = Employee::find($notification->recipient_id);
        } elseif ($notification->recipient_type === User::class) {
            $recipient = User::find($notification->recipient_id);
        }

        if (!$recipient) {
            FilamentNotification::make()
                ->title('Recipient Not Found')
                ->body('The selected recipient could not be found.')
                ->danger()
                ->send();
            return;
        }

        // Send notification
        $success = false;
        if ($recipient instanceof Employee) {
            // Use Firestore tokens for employees
            $success = $notificationService->sendToEmployeeWithFirestoreTokens(
                $recipient->uid,
                $notification->title,
                $notification->body,
                $data,
                $options
            );
        } else {
            // Use regular method for users
            $success = $notificationService->sendToRecipient(
                $recipient,
                $notification->title,
                $notification->body,
                $data,
                $options
            );
        }

        if ($success) {
            // Update notification status
            $notification->update([
                'status' => NotificationModel::STATUS_SENT,
                'sent_at' => now(),
            ]);

            FilamentNotification::make()
                ->title('Notification Sent')
                ->body('The notification has been sent successfully to the recipient.')
                ->success()
                ->send();
        } else {
            // Update notification status
            $notification->update([
                'status' => NotificationModel::STATUS_FAILED,
            ]);

            FilamentNotification::make()
                ->title('Send Failed')
                ->body('Failed to send the notification. Check the logs for details.')
                ->danger()
                ->send();
        }
    }
}

<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Models\Employee;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification as FilamentNotification;

class EditNotification extends EditRecord
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resend')
                ->label('Resend Notification')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    $notification = $this->record;
                    
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

                    // Prepare data for FCM
                    $data = $notification->data ?? [];
                    $data['action'] = 'manual_notification';
                    $data['notification_id'] = $notification->id;
                    $data['created_via'] = 'filament_admin';
                    $data['resent'] = true;

                    $options = [
                        'type' => $notification->type,
                        'priority' => $notification->priority,
                        'image_url' => $notification->image_url,
                        'action_url' => $notification->action_url,
                    ];

                    // Send notification
                    $notificationService = new NotificationService();
                    
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
                        FilamentNotification::make()
                            ->title('Notification Resent')
                            ->body('The notification has been resent successfully.')
                            ->success()
                            ->send();
                    } else {
                        FilamentNotification::make()
                            ->title('Resend Failed')
                            ->body('Failed to resend the notification. Check the logs for details.')
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}

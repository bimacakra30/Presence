<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter notification title'),

                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Enter notification message'),

                        Forms\Components\Select::make('type')
                            ->options([
                                Notification::TYPE_GENERAL => 'General',
                                Notification::TYPE_PRESENCE => 'Presence',
                                Notification::TYPE_PERMIT => 'Permit',
                                Notification::TYPE_SALARY => 'Salary',
                                Notification::TYPE_ANNOUNCEMENT => 'Announcement',
                                Notification::TYPE_SYSTEM => 'System',
                            ])
                            ->default(Notification::TYPE_GENERAL)
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->options([
                                Notification::PRIORITY_LOW => 'Low',
                                Notification::PRIORITY_NORMAL => 'Normal',
                                Notification::PRIORITY_HIGH => 'High',
                                Notification::PRIORITY_URGENT => 'Urgent',
                            ])
                            ->default(Notification::PRIORITY_NORMAL)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Recipient Selection')
                    ->schema([
                        Forms\Components\Select::make('recipient_type')
                            ->options([
                                'App\Models\Employee' => 'Employee',
                                'App\Models\User' => 'User',
                            ])
                            ->reactive()
                            ->required(),

                        Forms\Components\Select::make('recipient_id')
                            ->label('Recipient')
                            ->options(function (callable $get) {
                                $recipientType = $get('recipient_type');
                                if (!$recipientType) return [];

                                $model = new $recipientType;
                                return $model::pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->visible(fn (callable $get) => $get('recipient_type') !== null),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Options')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')
                            ->url()
                            ->placeholder('https://example.com/image.jpg')
                            ->helperText('Optional image URL for the notification'),

                        Forms\Components\TextInput::make('action_url')
                            ->url()
                            ->placeholder('https://example.com/action')
                            ->helperText('Optional action URL when notification is tapped'),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Schedule for')
                            ->helperText('Leave empty to send immediately')
                            ->timezone('Asia/Jakarta'),

                        Forms\Components\KeyValue::make('data')
                            ->label('Additional Data')
                            ->helperText('Key-value pairs for mobile app')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('body')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => Notification::TYPE_GENERAL,
                        'success' => Notification::TYPE_PRESENCE,
                        'warning' => Notification::TYPE_PERMIT,
                        'info' => Notification::TYPE_SALARY,
                        'danger' => Notification::TYPE_ANNOUNCEMENT,
                        'secondary' => Notification::TYPE_SYSTEM,
                    ]),

                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'gray' => Notification::PRIORITY_LOW,
                        'blue' => Notification::PRIORITY_NORMAL,
                        'yellow' => Notification::PRIORITY_HIGH,
                        'red' => Notification::PRIORITY_URGENT,
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Notification::STATUS_PENDING,
                        'success' => Notification::STATUS_SENT,
                        'danger' => Notification::STATUS_FAILED,
                        'info' => Notification::STATUS_SCHEDULED,
                    ]),

                Tables\Columns\TextColumn::make('recipient.name')
                    ->label('Recipient')
                    ->searchable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('read_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Notification::TYPE_GENERAL => 'General',
                        Notification::TYPE_PRESENCE => 'Presence',
                        Notification::TYPE_PERMIT => 'Permit',
                        Notification::TYPE_SALARY => 'Salary',
                        Notification::TYPE_ANNOUNCEMENT => 'Announcement',
                        Notification::TYPE_SYSTEM => 'System',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Notification::STATUS_PENDING => 'Pending',
                        Notification::STATUS_SENT => 'Sent',
                        Notification::STATUS_FAILED => 'Failed',
                        Notification::STATUS_SCHEDULED => 'Scheduled',
                    ]),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        Notification::PRIORITY_LOW => 'Low',
                        Notification::PRIORITY_NORMAL => 'Normal',
                        Notification::PRIORITY_HIGH => 'High',
                        Notification::PRIORITY_URGENT => 'Urgent',
                    ]),

                Tables\Filters\Filter::make('unread')
                    ->query(fn (Builder $query): Builder => $query->whereNull('read_at'))
                    ->label('Unread Only'),

                Tables\Filters\Filter::make('failed')
                    ->query(fn (Builder $query): Builder => $query->where('status', Notification::STATUS_FAILED))
                    ->label('Failed Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Notification $record): bool => $record->status === Notification::STATUS_FAILED)
                    ->action(function (Notification $record) {
                        $notificationService = new NotificationService();
                        $success = $notificationService->sendToRecipient(
                            $record->recipient,
                            $record->title,
                            $record->body,
                            $record->data ?? [],
                            [
                                'type' => $record->type,
                                'priority' => $record->priority,
                                'image_url' => $record->image_url,
                                'action_url' => $record->action_url,
                            ]
                        );

                        if ($success) {
                            FilamentNotification::make()
                                ->title('Notification Resent')
                                ->body('The notification has been resent successfully.')
                                ->success()
                                ->send();
                        } else {
                            FilamentNotification::make()
                                ->title('Resend Failed')
                                ->body('Failed to resend the notification.')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Action::make('mark_as_read')
                        ->label('Mark as Read')
                        ->icon('heroicon-o-check')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->markAsRead();
                            });
                            
                            FilamentNotification::make()
                                ->title('Marked as Read')
                                ->body('Selected notifications have been marked as read.')
                                ->success()
                                ->send();
                        }),

                    Action::make('resend_failed')
                        ->label('Resend Failed')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            $notificationService = new NotificationService();
                            $successCount = 0;
                            $failCount = 0;

                            foreach ($records as $record) {
                                if ($record->status === Notification::STATUS_FAILED) {
                                    $success = $notificationService->sendToRecipient(
                                        $record->recipient,
                                        $record->title,
                                        $record->body,
                                        $record->data ?? [],
                                        [
                                            'type' => $record->type,
                                            'priority' => $record->priority,
                                            'image_url' => $record->image_url,
                                            'action_url' => $record->action_url,
                                        ]
                                    );

                                    if ($success) {
                                        $successCount++;
                                    } else {
                                        $failCount++;
                                    }
                                }
                            }

                            FilamentNotification::make()
                                ->title('Bulk Resend Complete')
                                ->body("Successfully resent: {$successCount}, Failed: {$failCount}")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $unreadCount = Notification::whereNull('read_at')->count();
        return $unreadCount > 0 ? $unreadCount : null;
    }
}

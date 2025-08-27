<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Services\FirestoreService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Widgets\StatsOverview;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationGroup = 'Management';
    protected static ?string $navigationBadgeTooltip = 'Jumlah Karyawan';
    protected static ?string $navigationLabel = 'Karyawan';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('photo')
                    ->label('Photo Profile')
                    ->directory('photos')
                    ->image()
                    ->imagePreviewHeight('200')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->visibility('public')
                    ->previewable()
                    ->nullable()
                    ->helperText('Photo will be stored locally and synced to Firestore automatically'),

                Forms\Components\TextInput::make('name')
                    ->label('Full Name')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, $component, $get, $set) {
                        // Auto generate username from name if creating new record
                        if ($component->getContainer()->getOperation() === 'create' && !$get('username')) {
                            $username = strtolower(str_replace([' ', '.'], ['_', '_'], $state));
                            $username = preg_replace('/[^a-z0-9_]/', '', $username);
                            $set('username', $username);
                        }
                    }),

                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules([
                        'regex:/^[a-zA-Z0-9_]+$/',
                    ])
                    ->helperText('Only letters, numbers, and underscores allowed'),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->hidden(fn ($context) => $context === 'edit')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->helperText('Minimum 8 characters. Will be hashed automatically.'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Confirm Password')
                    ->hidden(fn ($context) => $context === 'edit')
                    ->password()
                    ->required()
                    ->dehydrated(false),

                Forms\Components\TextInput::make('phone')
                    ->label('Phone Number')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->tel()
                    ->maxLength(20)
                    ->nullable()
                    ->rules([
                        'regex:/^[\+]?[0-9\-\s]+$/',
                    ])
                    ->helperText('Format: +62812345678 or 081234567890'),

                Forms\Components\Textarea::make('address')
                    ->label('Address')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->columnSpanFull()
                    ->nullable()
                    ->maxLength(500),

                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Date of Birth')
                    ->disabled(fn ($context) => $context === 'edit')
                    ->nullable()
                    ->maxDate(now()->subYears(17))
                    ->helperText('Employee must be at least 17 years old'),

                Forms\Components\TextInput::make('position')
                    ->label('Jabatan')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'aktif' => 'Active',
                        'non-aktif' => 'Inactive',
                        'terminated' => 'Terminated',
                    ])
                    ->default('aktif'),

                Forms\Components\Select::make('provider')
                    ->label('Login Provider')
                    ->options([
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        'twitter' => 'Twitter',
                        'github' => 'GitHub',
                        'email/password' => 'Email/Password',
                    ])
                    ->default('email/password')
                    ->required()
                    ->nullable(),

                Forms\Components\Placeholder::make('sync_info')
                    ->label('Synchronization')
                    ->content('Employee data will be automatically synchronized with Firestore and Firebase Auth when saved.')
                    ->visible(fn ($context) => $context === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('photo_display')
                    ->label('Photo')
                    ->html()
                    ->getStateUsing(function ($record) {
                        // Priority: profilePictureUrl (from sync) > photo (local) > initials
                        $photoUrl = $record->profilePictureUrl ?? $record->photo ?? null;
                        
                        if ($photoUrl) {
                            // Check if it's a full URL or local path
                            if (filter_var($photoUrl, FILTER_VALIDATE_URL)) {
                                $displayUrl = $photoUrl;
                            } else {
                                // Local storage path
                                $displayUrl = asset('storage/' . $photoUrl);
                            }
                            
                            return '<div class="flex justify-center">
                                        <img src="' . $displayUrl . '" 
                                             alt="Photo" 
                                             class="w-10 h-10 rounded-full object-cover border-2 border-gray-200 shadow-sm hover:scale-110 transition-transform duration-200"
                                             onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';"
                                             loading="lazy">
                                        <div class="w-10 h-10 rounded-full bg-gray-500 flex items-center justify-center text-white font-semibold text-sm border-2 border-gray-200 shadow-sm" style="display:none;">
                                            ' . static::generateInitials($record->name ?? '') . '
                                        </div>
                                    </div>';
                        }
                        
                        // Generate initials if no photo
                        $initials = static::generateInitials($record->name ?? '');
                        $bgColor = static::generateBackgroundColor($record->name ?? '');
                        
                        return '<div class="flex justify-center">
                                    <div class="w-10 h-10 rounded-full ' . $bgColor . ' flex items-center justify-center text-white font-semibold text-sm border-2 border-gray-200 shadow-sm hover:scale-110 transition-transform duration-200">
                                        ' . $initials . '
                                    </div>
                                </div>';
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Name copied')
                    ->copyMessageDuration(1500),
                    
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->prefix('@'),
                    
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->sortable()
                    ->toggleable()
                    ->copyable()
                    ->icon('heroicon-m-phone')
                    ->formatStateUsing(fn ($state) => $state ? $state : '-'),

                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label('Birth Date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $date = \Carbon\Carbon::parse($state);
                        return $date->format('d M Y') . ' (' . $date->age . ' yo)';
                    }),
                    
                Tables\Columns\TextColumn::make('position')
                    ->label('Jabatan')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'google' => 'danger',
                        'facebook' => 'info',
                        'twitter' => 'primary',
                        'github' => 'gray',
                        'email' => 'success',
                        default => 'secondary',
                    })
                    ->icon(fn (string $state): string => match (strtolower($state)) {
                        'google' => 'heroicon-m-globe-alt',
                        'facebook' => 'heroicon-m-globe-alt',
                        'twitter' => 'heroicon-m-globe-alt',
                        'github' => 'heroicon-m-globe-alt',
                        'email' => 'heroicon-m-envelope',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'non-aktif' => 'warning',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'aktif' => 'heroicon-m-check-circle',
                        'non-aktif' => 'heroicon-m-pause-circle',
                        'terminated' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'aktif' => 'Active',
                        'non-aktif' => 'Inactive',
                        'terminated' => 'Terminated',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Active',
                        'non-aktif' => 'Inactive',
                        'terminated' => 'Terminated',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'google' => 'Google',
                        'facebook' => 'Facebook',
                        'twitter' => 'Twitter',
                        'github' => 'GitHub',
                        'email' => 'Email/Password',
                    ]),
                Tables\Filters\Filter::make('firestore_synced')
                    ->label('Firestore Synced')
                    ->query(fn ($query) => $query->whereNotNull('firestore_id')),
                Tables\Filters\Filter::make('not_synced')
                    ->label('Not Synced')
                    ->query(fn ($query) => $query->whereNull('firestore_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Employee Details')
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Personal Information')
                            ->schema([
                                \Filament\Infolists\Components\ImageEntry::make('photo')
                                    ->label('Photo')
                                    ->circular()
                                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'),
                                \Filament\Infolists\Components\TextEntry::make('name')->label('Full Name'),
                                \Filament\Infolists\Components\TextEntry::make('username')->label('Username'),
                                \Filament\Infolists\Components\TextEntry::make('email')->label('Email'),
                                \Filament\Infolists\Components\TextEntry::make('phone')->label('Phone'),
                                \Filament\Infolists\Components\TextEntry::make('date_of_birth')->label('Date of Birth')->date(),
                                \Filament\Infolists\Components\TextEntry::make('address')->label('Address'),
                            ])
                            ->columns(2),
                        \Filament\Infolists\Components\Section::make('Employment Information')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('position')->label('Position'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'aktif' => 'success',
                                        'non-aktif' => 'warning',
                                        'terminated' => 'danger',
                                        default => 'gray',
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('provider')->label('Login Provider'),
                                \Filament\Infolists\Components\TextEntry::make('firestore_id')->label('Firestore ID'),
                            ])
                            ->columns(2),
                    ]),

                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('manual_firestore_sync')
                    ->label('Force Sync to Firestore')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('warning')
                    ->visible(fn ($record): bool => empty($record->firestore_id))
                    ->requiresConfirmation()
                    ->modalHeading('Force Sync Employee to Firestore')
                    ->modalDescription('This will manually create this employee in Firestore (bypassing automatic sync).')
                    ->action(function ($record) {
                        try {
                            $firestoreService = new FirestoreService();
                            
                            $data = [
                                'uid' => $record->uid ?: (string) \Illuminate\Support\Str::uuid(),
                                'profilePictureUrl' => $record->photo,
                                'name' => $record->name,
                                'username' => $record->username,
                                'email' => $record->email,
                                'address' => $record->address,
                                'dateOfBirth' => $record->date_of_birth ? $record->date_of_birth->toISOString() : null,
                                'position' => $record->position,
                                'status' => $record->status,
                                'provider' => $record->provider,
                                'createdAt' => now()->toISOString(),
                                'updatedAt' => now()->toISOString(),
                            ];

                            // Create in Firestore using the existing method
                            $collection = $firestoreService->getCollection();
                            $docRef = $collection->add($data);
                            
                            // Update local record
                            $record->update([
                                'firestore_id' => $docRef->id(),
                                'uid' => $data['uid']
                            ]);

                            Notification::make()
                                ->title('Successfully synced to Firestore')
                                ->body('Employee ' . $record->name . ' has been synced to Firestore')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Log::error('Manual Firestore sync failed: ' . $e->getMessage());
                            Notification::make()
                                ->title('Failed to sync to Firestore')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Employee')
                    ->modalDescription('Are you sure you want to delete this employee? This will also remove the data from Firestore and Firebase Auth if synced.')
                    ->successNotificationTitle('Employee deleted successfully'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_sync_from_firestore')
                        ->label('Sync Selected from Firestore')
                        ->icon('heroicon-o-arrow-down-on-square')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Sync Multiple Employees from Firestore')
                        ->modalDescription('This will sync all selected employees with their Firestore data.')
                        ->action(function ($records) {
                            $firestoreService = new FirestoreService();
                            $successCount = 0;
                            $errorCount = 0;

                            foreach ($records as $record) {
                                if ($record->uid) {
                                    try {
                                        $firestoreService->syncEmployeeByUid($record->uid);
                                        $successCount++;
                                    } catch (\Exception $e) {
                                        $errorCount++;
                                        Log::error("Failed to sync employee {$record->email}: " . $e->getMessage());
                                    }
                                }
                            }

                            Notification::make()
                                ->title('Bulk Sync Completed')
                                ->body("Successfully synced: {$successCount}, Failed: {$errorCount}")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Delete Selected')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Multiple Employees')
                        ->modalDescription('This will delete all selected employees from both local database and Firestore.')
                        ->successNotificationTitle('Employees deleted successfully'),
                ])
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_all_from_firestore')
                    ->label('Sync All from Firestore')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync All Employees from Firestore')
                    ->modalDescription('This will sync all employees from Firestore to the local database.')
                    ->action(function () {
                        try {
                            $firestoreService = new FirestoreService();
                            $result = $firestoreService->syncAllEmployees();
                            
                            Notification::make()
                                ->title('Sync All Completed')
                                ->body("Synced: {$result['synced']}, Updated: {$result['updated']}, Errors: " . count($result['errors']))
                                ->success()
                                ->send();

                            if (!empty($result['errors'])) {
                                foreach ($result['errors'] as $error) {
                                    Log::error("Sync error for {$error['user']}: {$error['error']}");
                                }
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('refresh_cache')
                    ->label('Clear Cache')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        $firestoreService = new FirestoreService();
                        $firestoreService->clearUsersCache();
                        
                        Notification::make()
                            ->title('Cache cleared')
                            ->body('Firestore cache has been cleared')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Generate initials from name
     */
    protected static function generateInitials(string $name): string
    {
        $name = trim($name);
        if (empty($name)) {
            return '??';
        }

        $words = explode(' ', $name);
        
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
        } elseif (count($words) == 1) {
            return strtoupper(substr($words[0], 0, 2));
        }
        
        return '??';
    }

    /**
     * Generate consistent background color based on name
     */
    protected static function generateBackgroundColor(string $name): string
    {
        $colors = [
            'bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500',
            'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-orange-500',
            'bg-teal-500', 'bg-cyan-500', 'bg-lime-500', 'bg-amber-500'
        ];
        
        $index = abs(crc32($name)) % count($colors);
        return $colors[$index];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            // Use local count to reduce Firestore requests
            return (string) Employee::count();
        } catch (\Exception $e) {
            return '0';
        }
    }

    public static function getWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
}
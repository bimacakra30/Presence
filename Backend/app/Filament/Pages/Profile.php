<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\User;

class Profile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profil Saya';
    protected static string $view = 'filament.pages.profile';
    protected static ?string $title = 'Profil Saya';
    
    public ?array $profileData = [];
    public ?array $passwordData = [];
    
    public function mount(): void
    {
        $this->fillForms();
    }
    
    protected function fillForms(): void
    {
        $user = User::find(Auth::id());
        
        $this->profileForm->fill([
            'photo' => $user->photo,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }
    
    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }
    
    public function profileForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->description('Ubah Informasi Akun Anda.')
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto Profil')
                            ->image()
                            ->avatar()
                            ->directory('profile-photos')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->circleCropper()
                            ->columnSpanFull(),
                        
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignorable: Auth::user())
                            ->maxLength(255),
                        
                        TextInput::make('role')
                            ->label('Role')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('profileData');
    }
    
    public function passwordForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Update Password')
                    ->description('Ensure your account is using a long, random password to stay secure.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->required()
                            ->currentPassword(),
                        
                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation'),
                        
                        TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('passwordData');
    }
    
    public function updateProfile(): void
    {
        try {
            $data = $this->profileForm->getState();
            
            User::where('id', Auth::id())->update($data);
            
            Notification::make()
                ->success()
                ->title('Profile updated successfully')
                ->send();
                
        } catch (Halt $exception) {
            return;
        }
    }
    
    public function updatePassword(): void
    {
        try {
            $data = $this->passwordForm->getState();
            
            User::where('id', Auth::id())->update([
                'password' => $data['password'],
            ]);
            
            $this->passwordForm->fill();
            
            Notification::make()
                ->success()
                ->title('Password updated successfully')
                ->send();
                
        } catch (Halt $exception) {
            return;
        }
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('updateProfile')
                ->label('Update Profile')
                ->submit('updateProfile'),
        ];
    }
    
    protected function getPasswordFormActions(): array
    {
        return [
            Action::make('updatePassword')
                ->label('Update Password')
                ->submit('updatePassword'),
        ];
    }
    
    public function getTitle(): string
    {
        return 'My Profile';
    }
}
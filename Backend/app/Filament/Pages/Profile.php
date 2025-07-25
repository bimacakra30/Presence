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
    protected static ?string $navigationLabel = 'Informasi Akun';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static string $view = 'filament.pages.profile';
    protected static ?int $navigationSort = 1;
    
    public ?array $profileData = [];
    
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
        ];
    }
    
    public function profileForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Profil')
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
    
    protected function getFormActions(): array
    {
        return [
            Action::make('updateProfile')
                ->label('Update Profile')
                ->submit('updateProfile'),
        ];
    }
    
    public function getTitle(): string
    {
        return 'My Profile';
    }
}
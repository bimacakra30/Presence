<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Halt;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\User;

class ChangePassword extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Ubah Password';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static string $view = 'filament.pages.change-password';
    protected static ?string $title = 'Ubah Password';
    protected static ?int $navigationSort = 2;

    public ?array $passwordData = [];

    public function mount(): void
    {
        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'passwordForm',
        ];
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ubah Password')
                    ->description('Gunakan password yang kuat dan mudah diingat.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Password Saat Ini')
                            ->password()
                            ->required()
                            ->currentPassword(),

                        TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('password_confirmation'),

                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi Password Baru')
                            ->password()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('passwordData');
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
                ->title('Password berhasil diperbarui')
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    protected function getFormActions(): array
{
    return [
        Action::make('updatePassword')
            ->label('Simpan Password')
            ->submit('updatePassword')
            ->color('danger')
            ->icon('heroicon-o-lock-closed')
            ->requiresConfirmation()
            ->modalHeading('Konfirmasi Perubahan Password')
            ->modalSubheading('Apakah Anda yakin ingin mengubah password?')
            ->modalButton('Ya, Simpan Password'),
    ];
}

}

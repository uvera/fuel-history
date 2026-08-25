<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAccess extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Access';

    protected string $view = 'filament.pages.api-access';

    public function getTitle(): string
    {
        return 'API Access (MCP)';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->generateTokenAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->currentUserTokens())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('created_at')->label('Created')->dateTime(),
                TextColumn::make('last_used_at')->label('Last used')->dateTime()->placeholder('Never'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke this token?')
                    ->modalDescription('Any MCP client using it will immediately lose access.')
                    ->action(fn (array $record) => $this->revokeToken((int) $record['id'])),
            ]);
    }

    /**
     * @return array<int, array{__key: string, id: int, name: string, created_at: ?string, last_used_at: ?string}>
     */
    private function currentUserTokens(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                // Array-backed table records need a "__key" entry for
                // Filament's own row/action resolution (Filament\Support\
                // ArrayRecord::getKeyName() defaults to "__key", not "id"
                // -- confirmed by reading that class directly after a
                // table-action test threw ActionNotResolvableException).
                '__key' => (string) $token->id,
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
            ])
            ->all();
    }

    private function revokeToken(int $tokenId): void
    {
        /** @var User $user */
        $user = auth()->user();

        // Scoped to the current user's own tokens -- can never revoke
        // another user's token by guessing an ID.
        $user->tokens()->whereKey($tokenId)->delete();

        Notification::make()
            ->title('Token revoked')
            ->success()
            ->send();
    }

    private function generateTokenAction(): Action
    {
        return Action::make('generate')
            ->label('Generate token')
            ->icon('heroicon-o-plus')
            ->form([
                TextInput::make('name')
                    ->label('Name')
                    ->placeholder('e.g. Claude Desktop, Cursor')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();

                $token = $user->createToken($data['name'], ['mcp:use'])->plainTextToken;
                $url = url('/mcp/fuel-history');

                // Sanctum never stores or re-displays the plaintext value
                // after this point -- shown once, in a notification that
                // doesn't auto-dismiss, so there's time to copy it.
                Notification::make()
                    ->title('Token generated -- copy it now, it will not be shown again')
                    ->body("URL: {$url}\nAuthorization: Bearer {$token}")
                    ->success()
                    ->persistent()
                    ->send();

                $this->resetTable();
            });
    }
}

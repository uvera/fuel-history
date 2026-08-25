<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The acting admin is always the new user's creator — never a form
     * field, never trusted from client input, and deliberately not on
     * User's mass-assignable attribute list (see User::$fillable). Set
     * directly on the model after creation instead of via form data, so
     * it can never be influenced by anything the form schema exposes.
     */
    protected function afterCreate(): void
    {
        /** @var User $record */
        $record = $this->record;

        $record->created_by = auth()->id();
        $record->save();
    }
}

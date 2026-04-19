<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = 'Admin';
    protected static ?string $pluralLabel = 'Admins';
    protected static ?string $label = 'Admin';
    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-user-group";

    protected static ?string $recordTitleAttribute = 'User';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Kontrol apakah user boleh mengakses halaman create
     */
    public static function canCreate(): bool
    {
        return Gate::allows('create', User::class);
    }

    /**
     * Method ini TIDAK mengontrol visibility button di table
     * Tapi mengontrol apakah user boleh BENAR-BENAR mengakses halaman edit via URL
     * 
     * Jika return false, user yang coba akses /users/{id}/edit langsung
     * akan dapat 403 Forbidden
     */
    public static function canEdit(Model $record): bool
    {
        // return Gate::allows('update', $record);
        return true;
    }

    /**
     * Kontrol apakah user benar-benar boleh delete via URL/backend
     */
    public static function canDelete(Model $record): bool
    {
        // return Gate::allows('delete', $record);
        return true;
    }

    /**
     * Kontrol apakah user boleh view detail
     */
    public static function canView(Model $record): bool
    {
        // return Gate::allows('view', $record);
        return true;
    }
}

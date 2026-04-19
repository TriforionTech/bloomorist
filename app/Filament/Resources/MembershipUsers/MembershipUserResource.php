<?php

namespace App\Filament\Resources\MembershipUsers;

use App\Filament\Resources\MembershipUsers\Pages\CreateMembershipUser;
use App\Filament\Resources\MembershipUsers\Pages\EditMembershipUser;
use App\Filament\Resources\MembershipUsers\Pages\ListMembershipUsers;
use App\Filament\Resources\MembershipUsers\Schemas\MembershipUserForm;
use App\Filament\Resources\MembershipUsers\Tables\MembershipUsersTable;
use App\Models\MembershipUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MembershipUserResource extends Resource
{
    protected static ?string $model = MembershipUser::class;
    protected static ?string $navigationLabel = 'Member';
    protected static ?string $pluralLabel = 'Members';
    protected static ?string $label = 'Member';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MembershipUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipUsersTable::configure($table);
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
            'index' => ListMembershipUsers::route('/'),
            'create' => CreateMembershipUser::route('/create'),
            'edit' => EditMembershipUser::route('/{record}/edit'),
        ];
    }
}

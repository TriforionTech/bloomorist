<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('NO.')
                    ->rowIndex(),
                TextColumn::make('name')
                    ->label('NAME')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('EMAIL')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('is_super_admin')
                    ->label('ROLE')
                    ->formatStateUsing(fn ($state) => $state ? 'SUPER ADMIN' : 'ADMIN')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'info')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('CREATED AT')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('UPDATED AT')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_super_admin')
                    ->label('Role')
                    ->options([
                        0 => 'Admin',
                        1 => 'Super Admin',
                    ])
                    ->searchable(),
            ])
            ->recordActionsColumnLabel('ACTIONS')
            ->recordActions([
                EditAction::make()
                    // Button SELALU muncul
                    ->authorize(fn () => true)
                    ->visible(fn () => true)
                    // Disabled based on permission
                    ->disabled(fn ($record) => Gate::denies('update', $record))
                    ->tooltip(function ($record) {
                        $response = Gate::inspect('update', $record);
                        return $response->denied() ? $response->message() : 'Edit this admin';
                    })
                    ->icon(function ($record) {
                        return Gate::inspect('update', $record)->denied() 
                            ? 'heroicon-o-lock-closed' 
                            : 'heroicon-o-pencil';
                    })
                    ->color(function ($record) {
                        return Gate::inspect('update', $record)->denied() 
                            ? 'gray' 
                            : 'primary';
                    })
                    // BACKEND GUARD
                    ->before(function ($action, $record) {
                        $response = Gate::inspect('update', $record);
                        
                        if ($response->denied()) {
                            Notification::make()
                                ->warning()
                                ->title('Access Denied')
                                ->body($response->message())
                                ->send();
                            
                            $action->halt();
                        }
                    }),
                DeleteAction::make()
                    // Button SELALU muncul
                    ->authorize(fn () => true)
                    ->visible(fn () => true)
                    // Disabled based on permission
                    ->disabled(fn ($record) => Gate::inspect('delete', $record)->denied())
                    ->tooltip(function ($record) {
                        $response = Gate::inspect('delete', $record);
                        return $response->denied() ? $response->message() : 'Delete this admin';
                    })
                    ->icon(function ($record) {
                        return Gate::inspect('delete', $record)->denied() 
                            ? 'heroicon-o-lock-closed' 
                            : 'heroicon-o-trash';
                    })
                    ->color(function ($record) {
                        return Gate::inspect('delete', $record)->denied() 
                            ? 'gray' 
                            : 'danger';
                    })
                    // BACKEND GUARD
                    ->before(function ($action, $record) {
                        $response = Gate::inspect('delete', $record);
                        
                        if ($response->denied()) {
                            Notification::make()
                                ->danger()
                                ->title('Action Denied')
                                ->body($response->message())
                                ->send();
                            
                            $action->halt();
                        }
                    })
                    ->modalHeading('Hapus Admin')
                    ->modalDescription('Apakah Anda yakin ingin menghapus admin ini? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->modalCancelActionLabel('Batal'),                    
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Filament::auth()->user()?->is_super_admin)
                        ->before(function ($action) {
                            $currentUser = Filament::auth()->user();
                            
                            if (!$currentUser->is_super_admin) {
                                Notification::make()
                                    ->danger()
                                    ->title('Access Denied')
                                    ->body('Only Superadmin can delete users.')
                                    ->send();
                                
                                $action->halt(); // hentikan paksa
                                return;
                            }
                        })
                        ->action(function (Collection $records) {
                            $currentUser = Filament::auth()->user();
                            $deletedCount = 0;
                            $skippedCount = 0;
                            
                            foreach ($records as $record) {
                                // Skip current user
                                if ($record->id === $currentUser->id) {
                                    $skippedCount++;
                                    continue;
                                }
                                
                                // // Skip other superadmins
                                // if ($record->is_super_admin) {
                                //     $skippedCount++;
                                //     continue;
                                // }
                                
                                // Delete jika lolos semua validasi
                                if (Gate::allows('delete', $record)) {
                                    $record->delete();
                                    $deletedCount++;
                                } else {
                                    $skippedCount++;
                                }
                            }
                            
                            // Notification based on result
                            if ($deletedCount > 0 && $skippedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Partially Deleted')
                                    ->body("{$deletedCount} user(s) deleted. {$skippedCount} user(s) skipped (yourself or other Superadmins).")
                                    ->send();
                            } elseif ($deletedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Deleted')
                                    ->body("{$deletedCount} user(s) deleted successfully.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('No Users Deleted')
                                    ->body('Selected users cannot be deleted (yourself or other Superadmins).')
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->recordUrl(function ($record) {
                $user = Filament::auth()->user();

                if (!$user) {
                    return null;
                }

                // Cek apakah user boleh edit record ini
                if (Gate::allows('update', $record)) {
                    return route('filament.admin.resources.users.edit', $record);
                }

                // Jika tidak boleh edit, redirect ke view
                return route('filament.admin.resources.users.view', $record);
            });
    }
}

import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { apiFetch } from '@/lib/api';
import { usePermission } from '@/lib/auth';
import { getQuery } from '@/lib/utils';
import { SharedData, type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { CirclePlusIcon, Database, Unlink } from 'lucide-react';
import { useState } from 'react';
import { AppTooltip } from './app-tooltip';
import AppearanceToggleDropdown from './appearance-dropdown';
import { DatabaseCommand } from './database-command';
import { CreateDatabaseProps, ModalCreateDatabase } from './modals/modal-create-database';
import { TeamSwitcher } from './team-switcher';
import { Button } from './ui/button';
import { ComboboxOption } from './ui/combobox';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {

    const { can } = usePermission();
    const { databases, groups: databaseGroups, auth } = usePage<SharedData>().props;
    const database = getQuery('database', 'No database selected');
    const groupedDatabases = (databaseGroups || [])
        .map?.(group => ({
            label: group.name,
            value: group.id.toString()
        }))
        ?.sort((a, b) => Number(b.value) - Number(a.value)) || [];
    const [groups, setGroups] = useState<ComboboxOption[]>(groupedDatabases);

    const handleSelectDatabase = (database: string) => {
        localStorage.setItem('sidebar', 'false');
        localStorage.setItem('prevUrl', window.location.href);
        router.get('/database-studio?database=' + database);
    }

    const handleCreateGroup = async (name: string): Promise<string> => {
        try {
            const teamId = localStorage.getItem('currentTeamId');
            const response = await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name, team_id: teamId }),
            });

            if (!response.ok) {
                throw new Error('Failed to create group');
            }

            const data = await response.json();
            const newGroupId = data.group.id as string;
            const newGroup: ComboboxOption = { value: newGroupId, label: name };
            setGroups((prev) => [...prev, newGroup].sort((a, b) => Number(b.value) - Number(a.value)));
            return newGroupId;
        } catch (error) {
            console.error("Error creating group:", error);
            throw error;
        }
    }

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const teamId = localStorage.getItem('currentTeamId');

        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
            groupId: Number(formData.groupId),
            teamId: Number(teamId),
        };

        const response = await apiFetch(route('database.create'), {
            method: 'POST',
            body: JSON.stringify(submittedData),
        });

        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }
    }

    const handleDisconnectDatabase = () => {
        localStorage.setItem('sidebar', 'true');
        router.visit(route('dashboard'));
    }

    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center border-b px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14">
            <div className="flex w-full items-center justify-between gap-2">
                {/* Left Section */}
                <div className="flex flex-1 items-center gap-2 min-w-0">
                    <SidebarTrigger className="-ml-1.5 md:-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>

                {/* Right Section */}
                <div className="flex flex-shrink-0 items-center gap-2">
                    {can('manage-databases') && (
                        <ModalCreateDatabase
                            existingDatabases={databases}
                            onSubmit={handleDatabaseSubmit}
                            groups={groups}
                            onCreateGroup={handleCreateGroup}
                        >
                            <AppTooltip text='Create new database'>
                                <Button variant={'outline'} size="sm" className="h-8 w-8 p-0">
                                    <CirclePlusIcon className='h-4 w-4' />
                                </Button>
                            </AppTooltip>
                        </ModalCreateDatabase>
                    )}

                    <DatabaseCommand
                        databases={databases}
                        onSelect={(database) => handleSelectDatabase(database.database_name)}
                    />

                    {database !== 'No database selected' && (
                        <div className='hidden md:flex items-center gap-1.5 bg-muted/50 px-2 py-1 rounded-md'>
                            <Database className='h-4 w-4 flex-shrink-0' />
                            <span className="truncate max-w-[120px]">{database}</span>
                            <AppTooltip text='Disconnect'>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    className='h-6 w-6 p-0'
                                    onClick={handleDisconnectDatabase}
                                >
                                    <Unlink className='h-3.5 w-3.5' />
                                </Button>
                            </AppTooltip>
                        </div>
                    )}

                    <div className="flex items-center gap-2 ml-2 pl-2 border-l">
                        <TeamSwitcher />
                    </div>
                </div>
                <AppearanceToggleDropdown />
            </div>
        </header>
    );
}

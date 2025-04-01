import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { apiFetch } from '@/lib/api';
import { getQuery } from '@/lib/utils';
import { SharedData, Team, type BreadcrumbItem as BreadcrumbItemType } from '@/types';
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
        router.get('/dashboard?database=' + database);
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
            groupId: Number(formData.groupName),
            teamId: Number(teamId),
        };

        router.post(route('database.create'), submittedData, {
            onSuccess: async () => {
                const teams = auth.user.teams
                if (teams.length > 0) {
                    const team = teams.find(team => team.id === Number(teamId)) as Team
                    await apiFetch(route('api.teams.databases', team.id))
                }
            },
            onFinish: () => router.visit(window.location.href)
        });
    }

    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-4">
            <div className="flex justify-between items-center gap-2 w-full">
                <div className='flex items-center'>
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <div className='flex items-center gap-3'>
                    <ModalCreateDatabase
                        existingDatabases={databases}
                        onSubmit={handleDatabaseSubmit}
                        groups={groups}
                        onCreateGroup={handleCreateGroup}
                    >
                        <AppTooltip text='Create new database'>
                            <Button variant={'outline'}>
                                <CirclePlusIcon className='h-4 w-4' />
                            </Button>
                        </AppTooltip>
                    </ModalCreateDatabase>
                    <DatabaseCommand databases={databases} onSelect={(database) => handleSelectDatabase(database.database_name)} />
                    {database !== 'No database selected' && (
                        <div className='flex items-center'>
                            <Database className='h-4 w-4 mr-1' />
                            <span>{database}</span>
                            <AppTooltip text='Disconnect'>
                                <Button variant="destructive" size="sm" className='ml-2' aria-label='Disconnect' onClick={() => router.get(route('dashboard'))}>
                                    <Unlink className='h-4 w-4' />
                                    <span className='sr-only'>Disconnect</span>
                                </Button>
                            </AppTooltip>
                        </div>
                    )}
                    <div className="flex items-centerml-2 pl-2 ml-2 border-l">
                        <TeamSwitcher />
                        <AppearanceToggleDropdown />
                    </div>
                </div>
            </div>
        </header>
    );
}

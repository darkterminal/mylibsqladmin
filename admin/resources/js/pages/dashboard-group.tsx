import GroupDetail from "@/components/group-detail";
import { ModalCreateGroupOnly } from "@/components/modals/modal-create-group-only";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useCustomEvent } from "@/hooks/use-custom-event";
import AppLayout from "@/layouts/app-layout";
import { apiFetch } from "@/lib/api";
import {
    UserDatabaseTokenProps,
    type BreadcrumbItem,
    type DatabaseInGroupProps,
    type GroupDatabaseProps
} from "@/types";
import { Head, router } from "@inertiajs/react";
import { Users2Icon } from "lucide-react";
import { useEffect, useState } from "react";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Groups',
        href: '/dashboard/groups',
    }
];

export default function DashboardGroup({
    databaseGroups,
    databaseNotInGroup,
}: {
    databaseGroups: GroupDatabaseProps[],
    databaseNotInGroup: DatabaseInGroupProps[]
}) {
    const [groups, setGroups] = useState<GroupDatabaseProps[]>(databaseGroups);
    const [selectedGroup, setSelectedGroup] = useState<GroupDatabaseProps | null>(null);

    useEffect(() => {
        setGroups(databaseGroups);
        if (!databaseGroups.find(g => g.id === selectedGroup?.id)) {
            setSelectedGroup(null);
        }
    }, [databaseGroups, selectedGroup]);

    const handleGroupClick = (group: (typeof databaseGroups)[0]) => {
        setSelectedGroup(group)
    }

    const handleCreateGroup = async (name: string) => {
        try {
            const teamId = localStorage.getItem('currentTeamId');
            const response = await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name, team_id: teamId }),
            });

            if (!response.ok) {
                throw new Error('Failed to create group');
            }

            const refreshSession = await apiFetch(route('api.teams.databases', Number(teamId)));

            if (!refreshSession.ok) {
                throw new Error('Failed to refresh session');
            }

            router.visit(window.location.href, {
                preserveScroll: true,
            });
        } catch (error) {
            console.error("Error creating group:", error);
            throw error;
        }
    }

    useCustomEvent('token-created-from-group', ({ groupId, databaseId, newToken }: { groupId: number, databaseId: number, newToken: UserDatabaseTokenProps }) => {
        setGroups(prev => prev.map(g => {
            if (g.id === groupId) {
                const exists = g.database_tokens.some(t => t.database_id === databaseId);

                return {
                    ...g,
                    database_tokens: exists ? g.database_tokens : [
                        ...g.database_tokens,
                        {
                            ...newToken,
                            database_id: databaseId
                        }
                    ]
                }
            }
            return g;
        }));
        setSelectedGroup(prev => prev?.id === groupId ? { ...prev, database_tokens: [...prev.database_tokens, newToken] } : prev);
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Group" />
            <div className="container mx-auto p-8">
                <h1 className="text-2xl font-bold mb-6">Database Groups Management</h1>

                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-xl font-semibold">Groups</h2>
                    <ModalCreateGroupOnly
                        trigger={
                            <Button variant="default">
                                <Users2Icon className="mr-1 h-4 w-4" /> New group
                            </Button>
                        }
                        onSave={(groupName) => handleCreateGroup(groupName)}
                    />
                </div>

                <div className="grid md:grid-cols-3 gap-6">
                    <div className="md:col-span-1">
                        <ScrollArea className="h-[700px]">
                            <div className="space-y-3">
                                {groups.map((group) => (
                                    <div
                                        key={group.id}
                                        className={`p-4 border rounded-md cursor-pointer transition-colors ${selectedGroup?.id === group.id ? "bg-primary/10 border-primary" : "hover:bg-muted"
                                            }`}
                                        onClick={() => handleGroupClick(group)}
                                    >
                                        <h3 className="font-medium">{group.name}</h3>
                                        <p className="text-sm text-muted-foreground">
                                            {group.members_count} {group.members_count === 1 ? "database" : "databases"}
                                        </p>
                                    </div>
                                ))}

                                {groups.length === 0 && (
                                    <div className="p-4 border rounded-md text-center text-muted-foreground">
                                        No groups yet. Create your first group!
                                    </div>
                                )}
                            </div>
                        </ScrollArea>
                    </div>

                    <div className="md:col-span-2">
                        {selectedGroup ? (
                            <GroupDetail
                                group={selectedGroup}
                                availableDatabases={databaseNotInGroup}
                            />
                        ) : (
                            <div className="h-full flex items-center justify-center border rounded-md p-8 text-center">
                                <div>
                                    <h3 className="font-medium mb-2">Select a group to view details</h3>
                                    <p className="text-sm text-muted-foreground">Click on a group from the list to view its databases</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}

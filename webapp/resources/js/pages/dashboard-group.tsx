import GroupDetail from "@/components/group-detail";
import { ModalCreateGroupOnly } from "@/components/modals/modal-create-group-only";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useCustomEvent } from "@/hooks/use-custom-event";
import AppLayout from "@/layouts/app-layout";
import { apiFetch } from "@/lib/api";
import { usePermission } from "@/lib/auth";
import {
    SharedData,
    type BreadcrumbItem,
    type DatabaseInGroupProps,
    type GroupDatabaseProps,
    type GroupMemberTokenProps,
} from "@/types";
import { Head, router, usePage } from "@inertiajs/react";
import { FolderOpen, KeyRound, Users2Icon } from "lucide-react";
import { useEffect, useState } from "react";
import { getCurrentTeamId } from "@/lib/current-team";
import { toast } from "sonner";;

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
    const { can } = usePermission();
    const { csrfToken } = usePage<SharedData>().props;
    const [groups, setGroups] = useState<GroupDatabaseProps[]>(databaseGroups);
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(() => databaseGroups[0]?.id ?? null);

    const selectedGroup = groups.find((group) => group.id === selectedGroupId) ?? null;

    useEffect(() => {
        setGroups(databaseGroups);
    }, [databaseGroups]);

    useEffect(() => {
        if (selectedGroupId === null && databaseGroups.length > 0) {
            setSelectedGroupId(databaseGroups[0].id);
        }
    }, [databaseGroups, selectedGroupId]);

    const handleCreateGroup = async (name: string): Promise<boolean> => {
        try {
            const teamId = getCurrentTeamId();

            if (!teamId) {
                toast.error('Select a team first');
                return false;
            }

            // apiFetch throws with the server's message on non-2xx — that is
            // the contract; do not double-handle response.ok here.
            await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name, team_id: teamId }),
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            // Best-effort session refresh — the groups page reloads anyway.
            await apiFetch(route('api.teams.databases', Number(teamId)), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            }).catch(() => { /* non-fatal */ });

            router.visit(window.location.href, {
                preserveScroll: true,
            });

            return true;
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Failed to create group');
            return false;
        }
    }

    useCustomEvent('token-created-from-group', ({ groupId, databaseId, newToken }: { groupId: number, databaseId: number, newToken: import('@/types').UserDatabaseTokenProps }) => {
        setGroups(prev => prev.map(g => {
            if (g.id !== groupId) {
                return g;
            }

            const adapted: GroupMemberTokenProps = {
                id: newToken.id,
                name: newToken.name,
                full_access_token: newToken.full_access_token,
                read_only_token: newToken.read_only_token,
                fingerprint: newToken.fingerprint,
                expiration_day: newToken.expiration_day,
                expires_at: newToken.expires_at,
                expiration_state: newToken.expiration_state,
                days_remaining: newToken.days_remaining,
                created_at: newToken.created_at,
                database_id: databaseId,
                user_id: newToken.user_id,
            };

            const exists = g.database_tokens.some(t => t.database_id === databaseId);

            return {
                ...g,
                database_tokens: exists ? g.database_tokens : [...g.database_tokens, adapted]
            };
        }));
    });

    const createGroupButton = can('create-groups') && (
        <ModalCreateGroupOnly
            trigger={
                <Button variant="default">
                    <Users2Icon className="mr-1 h-4 w-4" aria-hidden="true" /> New group
                </Button>
            }
            onSave={(group) => handleCreateGroup(group.groupName)}
        />
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Groups" />
            <div className="min-w-0 w-full">
                <div className="container mx-auto flex w-full min-w-0 flex-col gap-4 p-4 sm:p-6 lg:p-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Database Groups</h1>
                            <p className="text-sm text-muted-foreground">
                                {groups.length} {groups.length === 1 ? 'group' : 'groups'} · share one credential across several databases
                            </p>
                        </div>
                        {groups.length > 0 && createGroupButton}
                    </div>

                    {groups.length === 0 ? (
                        <div className="flex h-80 flex-col items-center justify-center gap-3 text-center">
                            <div className="rounded-full bg-muted p-4">
                                <FolderOpen className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                            </div>
                            <h2 className="text-xl font-semibold">No groups yet</h2>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Groups give several databases a shared credential — create one, attach the
                                databases, and hand out a single token your apps authenticate with.
                            </p>
                            {createGroupButton}
                        </div>
                    ) : (
                        <div className="grid min-w-0 grid-cols-1 gap-6 md:grid-cols-3">
                            <div className="min-w-0 md:col-span-1">
                                <ScrollArea className="h-[70vh] min-h-48 pr-1">
                                    <div className="flex flex-col gap-2">
                                        {groups.map((group) => {
                                            const selected = selectedGroupId === group.id;

                                            return (
                                                <button
                                                    key={group.id}
                                                    type="button"
                                                    onClick={() => setSelectedGroupId(group.id)}
                                                    aria-pressed={selected}
                                                    className={`w-full rounded-md border p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${selected ? "border-primary bg-primary/10" : "hover:bg-muted"}`}
                                                >
                                                    <span className="flex items-center justify-between gap-2">
                                                        <span className="min-w-0 truncate font-medium" title={group.name}>
                                                            {group.name}
                                                        </span>
                                                        {group.has_token && (
                                                            <KeyRound className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                                                        )}
                                                    </span>
                                                    <span className="mt-0.5 block text-sm text-muted-foreground">
                                                        {group.members_count} {group.members_count === 1 ? "database" : "databases"}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </ScrollArea>
                            </div>

                            <div className="min-w-0 md:col-span-2">
                                {selectedGroup ? (
                                    <GroupDetail
                                        group={selectedGroup}
                                        availableDatabases={databaseNotInGroup}
                                    />
                                ) : (
                                    <div className="flex h-64 items-center justify-center rounded-md border p-8 text-center text-sm text-muted-foreground">
                                        Select a group to view its databases
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}

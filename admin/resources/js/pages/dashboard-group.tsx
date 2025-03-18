import GroupDetail from "@/components/group-detail";
import ModalCreateGroup from "@/components/modals/modal-create-group";
import { Button } from "@/components/ui/button";
import { useCustomEvent } from "@/hooks/use-custom-event";
import AppLayout from "@/layouts/app-layout";
import {
    DatabaseInGroupProps,
    type BreadcrumbItem,
    type GroupDatabaseProps
} from "@/types";
import { Head, router, usePage } from "@inertiajs/react";
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

    const { props } = usePage<{
        databaseGroups: GroupDatabaseProps[];
        databaseNotInGroup: DatabaseInGroupProps[];
        flash: {
            success?: string;
            newGroup?: GroupDatabaseProps;
        };
    }>();

    useCustomEvent('database-group-is-deleted', ({ id }: { id: number }) => {
        setGroups((prev) => prev.filter((group) => group.id !== id));
        setSelectedGroup(null);
        router.reload({ only: ['databaseGroups'] })
    });

    useEffect(() => {
        if (props.flash?.newGroup) {
            setGroups((prev) => [...prev, props.flash.newGroup!].reverse());
        }
    }, [props.flash?.newGroup]);

    const handleGroupClick = (group: (typeof databaseGroups)[0]) => {
        setSelectedGroup(group)
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Group" />
            <div className="container mx-auto p-8">
                <h1 className="text-2xl font-bold mb-6">Database Groups Management</h1>

                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-xl font-semibold">Groups</h2>
                    <ModalCreateGroup databases={databaseNotInGroup}>
                        <Button>
                            <Users2Icon className="h-4 w-4" /> Create New Group
                        </Button>
                    </ModalCreateGroup>
                </div>

                <div className="grid md:grid-cols-3 gap-6">
                    <div className="md:col-span-1">
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
                    </div>

                    <div className="md:col-span-2">
                        {selectedGroup ? (
                            <GroupDetail group={selectedGroup} availableDatabases={databaseNotInGroup} />
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

import { triggerEvent } from "@/hooks/use-custom-event";
import { groupDatabases } from "@/lib/utils";
import { type LibSQLDatabases } from "@/types";
import { router } from "@inertiajs/react";
import { Cylinder, Database, DatabaseIcon, Eye, GitBranch, Trash } from "lucide-react";
import React, { useCallback } from "react";
import { toast } from "sonner";
import { AppContextMenu, type ContextMenuItemProps } from "./app-context-menu";
import { AppTooltip } from "./app-tooltip";
import { CreateDatabaseProps, ModalCreateDatabaseV2 } from "./modals/modal-create-database-v2";
import { Separator } from "./ui/separator";

export function AppDatabaseSidebar({ databaseName, userDatabases }: { databaseName: string | null, userDatabases: LibSQLDatabases[] }) {

    const { standalone, parents, childrenMap } = groupDatabases(userDatabases);

    const getContextMenuItems = useCallback((database: LibSQLDatabases): ContextMenuItemProps[] => {
        return [
            ...(Number(database.is_schema) ? [{
                title: 'Create Child Schema Database',
                icon: DatabaseIcon,
                onClick: () => {
                    triggerEvent('open-modal-changed', { isModalOpen: true, parentDatabase: database.database_name })
                }
            }] : []),
            {
                title: 'Delete',
                icon: Trash,
                onClick: () => {
                    toast.error(`Delete ${database.database_name}`, {
                        position: 'top-center',
                        action: {
                            label: 'Delete',
                            onClick: () => {
                                router.delete(`/databases/delete/${database.database_name}`, {
                                    onFinish: () => {
                                        router.get('/dashboard');
                                    }
                                });
                            }
                        }
                    });
                }
            }
        ];
    }, []);

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
        };

        router.post(route('database.create'), submittedData, {
            onSuccess: () => {
                toast.success('Database created successfully', {
                    position: 'top-center',
                    action: {
                        label: <><Eye className="mr-1 w-4 h-4" /> View Database</>,
                        onClick: () => {
                            router.get('/dashboard?database=' + submittedData.database);
                        },
                    }
                });
            }
        });
    }

    return (
        <div className="w-60 h-full bg-neutral-50 dark:bg-neutral-950 rounded-bl-lg border-r border-neutral-200 p-1 dark:border-neutral-800">
            <ul className="list-none p-0">
                <li className="flex items-center gap-2 px-2 p-1 mb-1">
                    <span className="text-sm font-semibold">Databases</span>
                </li>
                {userDatabases.length > 0 && (
                    <>
                        <Separator className='my-2' />
                        {standalone.map((database: LibSQLDatabases) => (
                            <AppContextMenu key={database.database_name} items={getContextMenuItems(database)}>
                                <li
                                    key={database.database_name}
                                    className={
                                        `flex justify-start items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm ${database.database_name === databaseName ? 'dark:bg-neutral-700 dark:hover:bg-neutral-800 dark:text-neutral-100 bg-neutral-200 hover:bg-neutral-300' : 'dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'}`
                                    }
                                    onClick={() => router.get('/dashboard', { database: database.database_name })}
                                >
                                    <Cylinder className="h-4 w-4" /> <span className={`text-sm ${database.database_name == databaseName ? 'font-semibold' : ''}`}>{database.database_name}</span>
                                </li>
                            </AppContextMenu>
                        ))}
                        {parents.map((parent) => (
                            <React.Fragment key={parent.database_name}>
                                <AppContextMenu key={parent.database_name} items={getContextMenuItems(parent)}>
                                    <li
                                        key={parent.database_name}
                                        className={
                                            `flex justify-start items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm ${parent.database_name === databaseName ? 'dark:bg-neutral-700 dark:hover:bg-neutral-800 dark:text-neutral-100 bg-neutral-200 hover:bg-neutral-300' : 'dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'}`
                                        }
                                        onClick={() => router.get('/dashboard', { database: parent.database_name })}
                                    >
                                        {(parent.is_schema === '1' || parent.is_schema !== '0') && (
                                            <AppTooltip text="Schema Database">
                                                <Database className="w-4 h-4" />
                                            </AppTooltip>
                                        )}
                                        <span className={`text-sm ${parent.database_name == databaseName ? 'font-semibold' : ''}`}>{parent.database_name}</span>
                                    </li>
                                </AppContextMenu>
                                {childrenMap.get(parent.database_name)?.map((child) => (
                                    <AppContextMenu key={child.database_name} items={getContextMenuItems(child)}>
                                        <li
                                            key={child.database_name}
                                            className={
                                                `ml-2 flex justify-start items-center gap-2 p-2 mb-1 cursor-pointer rounded-sm ${child.database_name === databaseName ? 'dark:bg-neutral-700 dark:hover:bg-neutral-800 dark:text-neutral-100 bg-neutral-200 hover:bg-neutral-300' : 'dark:hover:bg-neutral-700 dark:hover:text-neutral-100 hover:bg-neutral-300'}`
                                            }
                                            onClick={() => router.get('/dashboard', { database: child.database_name })}
                                        >
                                            {(child.is_schema === '1' || child.is_schema !== '0') && (
                                                <AppTooltip text={`Child of ${parent.database_name}`}>
                                                    <GitBranch className="w-4 h-4" />
                                                </AppTooltip>
                                            )}
                                            <span className={`text-sm ${child.database_name == databaseName ? 'font-semibold' : ''}`}>{child.database_name}</span>
                                        </li>
                                    </AppContextMenu>
                                ))}
                            </React.Fragment>
                        ))}
                    </>
                )}
            </ul>
            <ModalCreateDatabaseV2
                existingDatabases={parents}
                useExistingDatabase={true}
                onSubmit={handleDatabaseSubmit}
            />
        </div>
    )
}

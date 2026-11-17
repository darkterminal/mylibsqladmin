import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { databaseGroupType } from "@/lib/utils";
import {
    AllowedUser,
    type MostUsedDatabaseMinimalProps,
    type MostUsedDatabaseProps,
    type UserDatabaseTokenProps
} from "@/types";
import { router, useForm, usePage } from "@inertiajs/react";
import { Check, Copy, Cylinder, Database, GitBranch, ShieldCheck, TriangleAlert } from "lucide-react";
import React, { FormEventHandler, useState } from "react";
import { toast } from "sonner";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from "../ui/select";

type CreateTokenProps = {
    name: string;
    expiration: number;
    userId: number;
    databaseId: number | undefined;
}

type FlashMessageProps = {
    success?: string;
    error?: string;
    newToken?: UserDatabaseTokenProps;
}

function CopyRow({ label, value }: { label: string; value: string }) {
    // useCopyToClipboard composes keys as `${identifier}-${type}` which never
    // matches a bare prefix — the reveal panel tracks its own copied state.
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    };

    return (
        <div className="flex flex-col gap-1.5">
            <Label>{label}</Label>
            <div className="flex items-stretch gap-2">
                <code className="min-w-0 flex-1 break-all rounded-md border bg-muted px-2 py-2 font-mono text-[10px] leading-relaxed">
                    {value}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    aria-label={`Copy ${label}`}
                    onClick={copy}
                >
                    {copied ? <Check className="h-4 w-4 text-primary" /> : <Copy className="h-4 w-4" />}
                </Button>
            </div>
        </div>
    );
}

export function ModalCreateToken({
    children,
    users = [],
    mostUsedDatabases,
    onCreateSuccess
}: {
    children: React.ReactNode,
    users?: AllowedUser[],
    mostUsedDatabases: MostUsedDatabaseProps[] | MostUsedDatabaseMinimalProps[],
    onCreateSuccess?: () => void
}) {

    const { props } = usePage();
    const [selectedDatabase, setSelectedDatabase] = useState<string | undefined>(mostUsedDatabases.length > 0 ? String(mostUsedDatabases[0].database_id) : undefined)
    const [selectedUser, setSelectedUser] = useState<string | undefined>(String(props.auth.user.role != "Database Maintainer" ? users.find((user) => user.id === props.auth.user.id)?.id : users[0]?.id))
    const { standaloneDatabases, parentDatabases, childDatabases } = databaseGroupType(mostUsedDatabases)
    const flash = props.flash as FlashMessageProps;
    const [isOpen, setOpen] = useState(false);
    const [submittedDbName, setSubmittedDbName] = useState<string | null>(null);
    const { data, setData, post, processing, errors, reset } = useForm<CreateTokenProps>({
        name: '',
        expiration: 0,
        userId: Number(selectedUser),
        databaseId: Number(selectedDatabase)
    });

    // The plaintext token pair travels exactly one Inertia round-trip via
    // flash.newToken — render it in place instead of closing the dialog.
    const createdToken = flash.newToken ?? null;
    const revealing = createdToken !== null;

    const selectedMeta = mostUsedDatabases.find((db) => String(db.database_id) === selectedDatabase);
    const isRotation = Boolean(selectedMeta && 'is_tokenized' in selectedMeta && selectedMeta.is_tokenized);

    const handleSelectChange = (value: string) => {
        setSelectedDatabase(value)
        setData({ ...data, databaseId: Number(value) })
    }

    const handleSelectedUserChange = (value: string) => {
        setSelectedUser(value)
        setData({ ...data, userId: Number(value) })
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (!data.databaseId || isNaN(data.databaseId)) {
            toast.error('Invalid database selection');
            return;
        }

        const dbName = (mostUsedDatabases.find((db) => String(db.database_id) === String(data.databaseId))?.database_name ?? 'database')
            .replace(/\s+-\s+\((not-)?tokenized\)$/, '');
        setSubmittedDbName(dbName);

        post(route('token.create'), {
            preserveScroll: true,
            onSuccess: () => {
                // Do NOT close — the dialog swaps to the one-time reveal panel
                // once flash.newToken lands in the page props.
                reset('name', 'expiration');
                setSelectedDatabase(undefined);

                if (isRotation) {
                    toast.warning(`Token replaced for ${dbName}`, {
                        description: 'The previous credential for this database and user no longer works.',
                        position: 'bottom-center',
                        duration: 6000,
                    });
                } else {
                    toast.success(`Token created for ${dbName}`, {
                        position: 'bottom-center',
                        duration: 5000,
                    });
                }
            },
            onFinish: () => {
                onCreateSuccess?.();
            }
        });
    }

    const closeReveal = () => {
        setOpen(false);
        setSubmittedDbName(null);
        // A full reload re-resolves the shared flash props (now empty) —
        // an `only:` partial reload does not, and the stale one-time token
        // would keep the reveal panel mounted forever.
        router.reload();
    }

    return (
        <Dialog
            open={isOpen}
            onOpenChange={(open) => {
                if (!open && revealing) {
                    // Any close path (X, backdrop, Escape, the CTA) goes through
                    // here so the flash is consumed exactly once.
                    closeReveal();
                    return;
                }
                setOpen(open);
            }}
        >
            <DialogTrigger asChild onClick={() => setOpen(true)}>{children}</DialogTrigger>
            <DialogContent>
                {revealing ? (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-emerald-500" aria-hidden="true" />
                                Your token is ready
                            </DialogTitle>
                            <DialogDescription>
                                Token created for <span className="font-medium text-foreground">{submittedDbName ?? 'the selected database'}</span>.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex flex-col gap-4">
                            <div className="rounded-md border border-amber-500/60 bg-amber-50/60 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                <p className="font-semibold">Copy both tokens now — this is the only time they are shown.</p>
                                <p className="mt-1">Only SHA-256 hashes are stored from here on. If you lose them, you must generate new ones.</p>
                            </div>
                            <CopyRow label="Full Access Token" value={createdToken.full_access_token} />
                            <CopyRow label="Read-only Token" value={createdToken.read_only_token} />
                            <Button type="button" className="mt-1 w-full" onClick={closeReveal}>
                                I&apos;ve saved these tokens
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Generate New Database Token</DialogTitle>
                            <DialogDescription>
                                Creates a full-access / read-only credential pair for the selected database and user.
                            </DialogDescription>
                        </DialogHeader>
                        {flash.error && (
                            <div className="rounded-md border border-destructive/60 bg-destructive/10 p-3 text-xs text-destructive">
                                {flash.error}
                            </div>
                        )}
                        <form onSubmit={submit} autoComplete="off" className="flex flex-col gap-4">
                            <div className="w-full items-center gap-1.5">
                                <Label htmlFor="token-database" className="mb-1.5 block">Select Database</Label>
                                <Select value={selectedDatabase} onValueChange={handleSelectChange}>
                                    <SelectTrigger className="w-full" id="token-database">
                                        <SelectValue placeholder="Select database" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-96 overflow-y-auto">
                                        {standaloneDatabases.map((db) => (
                                            <SelectItem
                                                key={db.database_id}
                                                value={String(db.database_id)}
                                                className="flex items-center"
                                            >
                                                <Cylinder className="h-3 w-3 mr-2" />
                                                {db.database_name}
                                            </SelectItem>
                                        ))}

                                        {parentDatabases.map((parentDb) => {
                                            return (
                                                <div key={parentDb.database_id} className="border-t mt-2 pt-2">
                                                    <SelectItem
                                                        value={String(parentDb.database_id)}
                                                        className="font-medium text-primary/80 hover:bg-accent/50"
                                                    >
                                                        <Database className="h-3 w-3 mr-2" />
                                                        {parentDb.database_name} (Schema)
                                                    </SelectItem>

                                                    <div className="ml-4 border-l-2 border-muted pl-2">
                                                        {childDatabases.get(parentDb.database_name.split(' - ')[0])?.map(childDb => (
                                                            <SelectItem
                                                                key={childDb.database_id}
                                                                value={String(childDb.database_id)}
                                                                className="text-muted-foreground"
                                                            >
                                                                <GitBranch className="h-3 w-3 mr-2" />
                                                                {childDb.database_name}
                                                            </SelectItem>
                                                        ))}
                                                    </div>
                                                </div>
                                            )
                                        })}
                                    </SelectContent>
                                </Select>
                            </div>

                            {isRotation && (
                                <div className="flex items-start gap-2 rounded-md border border-amber-500/60 bg-amber-50/60 p-3 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200" role="alert">
                                    <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                    <p>
                                        A token already exists for this database and the selected user.
                                        Generating a new one <span className="font-semibold">replaces it</span> — the previous credential stops working immediately.
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-col w-full space-y-2">
                                <Label htmlFor="token-name">Token name <span className="font-normal text-muted-foreground">(optional)</span></Label>
                                <Input
                                    id="token-name"
                                    autoComplete="off"
                                    placeholder="e.g. ci-pipeline"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    tabIndex={1}
                                />
                                {errors.name && <span className="text-xs text-destructive">{errors.name}</span>}
                                <span className="text-muted-foreground text-xs">Leave blank and we’ll name it after the user.</span>
                            </div>
                            <div className="flex flex-col w-full space-y-2">
                                <Label htmlFor="granted-to">Select User</Label>
                                <Select value={selectedUser} onValueChange={handleSelectedUserChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users && users.map((user) => (
                                            <SelectItem key={user.id} value={String(user.id)}>
                                                {user.name === props.auth.user.name ? `Me - ${user.roles[0].name}` : <span>{user.name} - {user.roles[0].name}</span>}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex flex-col w-full space-y-2">
                                <Label htmlFor="expiration">Expiration (days)</Label>
                                <Input
                                    type="number"
                                    id="expiration"
                                    name="expiration"
                                    value={data.expiration}
                                    onChange={(e) => setData('expiration', Number(e.target.value))}
                                    placeholder="Expiration in a day"
                                    className="w-full"
                                    min={0}
                                    tabIndex={2}
                                    required
                                />
                                <span className="text-muted-foreground text-xs">
                                    Note: Use 0 for no expiration
                                </span>
                            </div>
                            <Button
                                variant={isRotation ? "destructive" : "default"}
                                type="submit"
                                disabled={processing || !data.databaseId}
                            >
                                {isRotation ? 'Generate (replace existing)' : 'Generate Token'}
                            </Button>
                        </form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

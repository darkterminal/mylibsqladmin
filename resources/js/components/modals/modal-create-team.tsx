import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { TeamForm } from "@/types";
import { useForm } from '@inertiajs/react';
import { useState } from "react";

export type TeamBase = {
    name: string;
    description: string;
};

type Props<T extends TeamBase> = {
    // Required props
    onSave: (team: T) => void;
    trigger: React.ReactNode;
    title?: string;
    description?: React.ReactNode;
    initialName?: string;
    initialDescription?: string;
    saveButtonLabel?: string;
    cancelButtonLabel?: string;
    nameLabel?: string;
    descriptionLabel?: string;
    namePlaceholder?: string;
    descriptionPlaceholder?: string;
    validate?: (team: TeamBase) => string | null;
};

export function ModalCreateTeam<T extends TeamBase>({
    onSave,
    trigger,
    title = "Create New Team",
    description = "Create a new team to collaborate with other users. Team members will have access to databases created under the team based on their assigned roles.",
    initialName = "",
    initialDescription = "",
    saveButtonLabel = "Create Team",
    cancelButtonLabel = "Cancel",
    nameLabel = "Name",
    descriptionLabel = "Description",
    namePlaceholder = "Engineering, Marketing, etc.",
    descriptionPlaceholder = "Briefly describe the team's purpose",
    validate,
}: Props<T>) {
    const [isOpen, setIsOpen] = useState(false);
    const { data, setData, reset, post, processing, errors } = useForm<TeamForm>({
        name: initialName,
        description: initialDescription,
    });

    const handleOpenChange = (open: boolean) => {
        if (!open) resetForm();
        setIsOpen(open);
    };

    const resetForm = () => {
        reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const validationError = validate?.({
            name: data.name.trim(),
            description: data.description.trim(),
        }) ?? null;

        if (validationError) {
            errors.name = validationError;
            return;
        }

        if (!data.name.trim()) {
            errors.name = "Team name is required";
            return;
        }

        onSave({
            name: data.name.trim(),
            description: data.description.trim(),
        } as T);

        resetForm();
        setIsOpen(false);
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <form autoComplete="off" onSubmit={handleSubmit}>
                    <div className="flex flex-col gap-4 py-4">
                        <div className="flex flex-col items-start space-y-4">
                            <Label htmlFor="team-name" className="text-right">
                                {nameLabel}
                            </Label>
                            <Input
                                id="team-name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="col-span-3"
                                placeholder={namePlaceholder}
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">{errors.name}</p>
                            )}
                        </div>

                        <div className="flex flex-col items-start space-y-4">
                            <Label htmlFor="team-description" className="text-right">
                                {descriptionLabel}
                            </Label>
                            <Textarea
                                id="team-description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="col-span-3"
                                placeholder={descriptionPlaceholder}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            {cancelButtonLabel}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : saveButtonLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

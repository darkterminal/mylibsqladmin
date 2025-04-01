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
import { useState } from "react";

export type TeamBase = {
    name: string;
    description: string;
};

type Props<T extends TeamBase> = {
    // Required props
    onSave: (team: T) => void;
    trigger: React.ReactNode;

    // Optional props with defaults
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
    const [name, setName] = useState(initialName);
    const [descriptionText, setDescription] = useState(initialDescription);
    const [error, setError] = useState<string | null>(null);

    const handleOpenChange = (open: boolean) => {
        if (!open) resetForm();
        setIsOpen(open);
    };

    const resetForm = () => {
        setName(initialName);
        setDescription(initialDescription);
        setError(null);
    };

    const handleSubmit = () => {
        const validationError = validate?.({
            name: name.trim(),
            description: descriptionText.trim(),
        }) ?? null;

        if (validationError) {
            setError(validationError);
            return;
        }

        if (!name.trim()) {
            setError("Team name is required");
            return;
        }

        onSave({
            name: name.trim(),
            description: descriptionText.trim(),
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

                <div className="flex flex-col gap-4 py-4">
                    <div className="flex flex-col items-start space-y-4">
                        <Label htmlFor="team-name" className="text-right">
                            {nameLabel}
                        </Label>
                        <Input
                            id="team-name"
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setError(null);
                            }}
                            className="col-span-3"
                            placeholder={namePlaceholder}
                        />
                    </div>

                    <div className="flex flex-col items-start space-y-4">
                        <Label htmlFor="team-description" className="text-right">
                            {descriptionLabel}
                        </Label>
                        <Textarea
                            id="team-description"
                            value={descriptionText}
                            onChange={(e) => setDescription(e.target.value)}
                            className="col-span-3"
                            placeholder={descriptionPlaceholder}
                        />
                    </div>

                    {error && (
                        <div className="text-red-500 text-sm col-span-4 text-center">
                            {error}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                    >
                        {cancelButtonLabel}
                    </Button>
                    <Button onClick={handleSubmit}>
                        {saveButtonLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

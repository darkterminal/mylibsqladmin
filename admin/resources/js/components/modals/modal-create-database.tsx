import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Button } from "../ui/button";
import { Input } from "../ui/input";

export function ModalCreateDatabase({ children }: { children: React.ReactNode }) {
    return (
        <Dialog>
            <DialogTrigger>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create New Database</DialogTitle>
                    <DialogDescription>
                        Enter the name of the database you want to create.
                    </DialogDescription>
                </DialogHeader>
                <div className="w-full items-center gap-1.5">
                    <Input type="text" id="database" placeholder="Database Name" className="w-full" required />
                </div>
                <Button variant={'default'}>Create Database</Button>
            </DialogContent>
        </Dialog>
    );
}

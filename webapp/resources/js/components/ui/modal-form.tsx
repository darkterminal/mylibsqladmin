"use client"

import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog"
import * as React from "react"

interface ModalFormProps {
    title: string
    description?: string
    children: React.ReactNode
    trigger: React.ReactNode
    submitLabel?: string
    isOpen?: boolean
    onOpenChange?: (open: boolean) => void
    onSubmit: (e: React.FormEvent) => Promise<void> | void
    isSubmitting?: boolean
    isSubmitDisabled?: boolean
}

export function ModalForm({
    title,
    description,
    children,
    trigger,
    submitLabel = "Submit",
    isOpen,
    onOpenChange,
    onSubmit,
    isSubmitting = false,
    isSubmitDisabled = false,
}: ModalFormProps) {
    const [open, setOpen] = React.useState(false)

    const handleOpenChange = (newOpen: boolean) => {
        if (onOpenChange) {
            onOpenChange(newOpen)
        } else {
            setOpen(newOpen)
        }
    }

    const currentOpen = isOpen !== undefined ? isOpen : open

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        await onSubmit(e)
    }

    return (
        <Dialog open={currentOpen} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild className="cursor-pointer hover:bg-primary-foreground">{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {children}
                    <Button type="submit" disabled={isSubmitting || isSubmitDisabled} className="w-full">
                        {isSubmitting ? "Processing..." : submitLabel}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    )
}


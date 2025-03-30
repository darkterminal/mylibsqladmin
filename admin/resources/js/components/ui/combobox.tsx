"use client"

import { Check, ChevronsUpDown, PlusCircle } from "lucide-react"
import * as React from "react"

import { Button } from "@/components/ui/button"
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from "@/components/ui/command"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

export type ComboboxOption = {
    value: string
    label: string
}

interface ComboboxProps {
    options: ComboboxOption[]
    value: string
    onValueChange: (value: string) => void
    placeholder?: string
    emptyMessage?: string
    createNewOptionLabel?: string
    onCreateOption?: (value: string) => void
    disabled?: boolean
    className?: string
}

export function Combobox({
    options,
    value,
    onValueChange,
    placeholder = "Select an option",
    emptyMessage = "No options found.",
    createNewOptionLabel = "Create new option",
    onCreateOption,
    disabled = false,
    className,
}: ComboboxProps) {
    const [open, setOpen] = React.useState(false)
    const [searchQuery, setSearchQuery] = React.useState("")

    // Add filtered options based on search query
    const filteredOptions = React.useMemo(() => {
        if (!searchQuery) return options
        return options.filter(option =>
            option.label.toLowerCase().includes(searchQuery.toLowerCase())
        )
    }, [options, searchQuery])

    const handleCreateOption = React.useCallback(() => {
        if (onCreateOption && searchQuery) {
            onCreateOption(searchQuery)
            setOpen(false)
            setSearchQuery("")
        }
    }, [onCreateOption, searchQuery])

    const selectedOption = React.useMemo(() => {
        return options.find((option) => option.value === value)
    }, [options, value])

    // Reset search when popover closes
    React.useEffect(() => {
        if (!open) {
            setSearchQuery("")
        }
    }, [open])

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn("w-full justify-between", className)}
                    disabled={disabled}
                >
                    {selectedOption ? selectedOption.label : placeholder}
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] p-0">
                <Command shouldFilter={false}>
                    <CommandInput
                        placeholder={`Search ${placeholder.toLowerCase()}...`}
                        value={searchQuery}
                        onValueChange={setSearchQuery}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {emptyMessage}
                            {onCreateOption && searchQuery && (
                                <Button variant="ghost" size="sm" className="mt-2 w-full justify-start" onClick={handleCreateOption}>
                                    <PlusCircle className="mr-2 h-4 w-4" />
                                    {createNewOptionLabel}: "{searchQuery}"
                                </Button>
                            )}
                        </CommandEmpty>
                        <CommandGroup>
                            {filteredOptions.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.value}
                                    onSelect={() => {
                                        onValueChange(option.value)
                                        setOpen(false)
                                    }}
                                >
                                    <Check className={cn("mr-2 h-4 w-4", value === option.value ? "opacity-100" : "opacity-0")} />
                                    {option.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                        {onCreateOption &&
                            searchQuery &&
                            !options.some((option) => option.label.toLowerCase() === searchQuery.toLowerCase()) && (
                                <CommandSeparator />
                            )}
                        {onCreateOption &&
                            searchQuery &&
                            !options.some((option) => option.label.toLowerCase() === searchQuery.toLowerCase()) && (
                                <CommandGroup>
                                    <CommandItem onSelect={handleCreateOption}>
                                        <PlusCircle className="mr-2 h-4 w-4" />
                                        {createNewOptionLabel}: "{searchQuery}"
                                    </CommandItem>
                                </CommandGroup>
                            )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    )
}

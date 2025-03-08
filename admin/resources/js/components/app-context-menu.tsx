import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { LucideProps } from "lucide-react";
import { Icon } from "./icon";

export type ContextMenuItemProps = {
    title: string;
    icon: React.ComponentType<LucideProps>;
    onClick: () => void;
    disabled?: boolean;
}

interface AppContextMenuProps {
    children: React.ReactNode;
    items: ContextMenuItemProps[];
}

export function AppContextMenu({ children, items }: AppContextMenuProps) {
    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>
                {children}
            </ContextMenuTrigger>
            <ContextMenuContent>
                {items.map((item) => (
                    <ContextMenuItem
                        key={item.title}
                        disabled={item.disabled}
                        onClick={item.onClick}
                        className="cursor-pointer"
                    >
                        <Icon iconNode={item.icon} className="h-5 w-5" />
                        <span>{item.title}</span>
                    </ContextMenuItem>
                ))}
            </ContextMenuContent>
        </ContextMenu>
    )
}

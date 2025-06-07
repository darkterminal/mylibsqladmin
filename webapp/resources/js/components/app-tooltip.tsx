import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger
} from "./ui/tooltip";

export function AppTooltip({ children, text, align = "center", ...props }: {
    children: React.ReactNode;
    text: string;
    align?: 'start' | 'center' | 'end';
}) {

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild {...props}>
                    {children}
                </TooltipTrigger>
                <TooltipContent align={align}>
                    <p>{text}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    )

}


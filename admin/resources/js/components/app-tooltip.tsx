import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger
} from "./ui/tooltip";

export function AppTooltip({ children, text, ...props }: {
    children: React.ReactNode;
    text: string;
}) {

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild {...props}>
                    {children}
                </TooltipTrigger>
                <TooltipContent>
                    <p>{text}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    )

}


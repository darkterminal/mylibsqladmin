import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { SDKOption, sdkOptions } from "@/lib/sdks"
import { ChevronDown, ExternalLink, PlugZap, StarIcon } from "lucide-react"

const handleSDKClick = (url: string) => {
    window.open(url, "_blank", "noopener,noreferrer")
}

const SDKItem = ({ sdk }: { sdk: SDKOption }) => (
    <div
        onClick={() => handleSDKClick(sdk.documentation)}
        className="cursor-pointer p-3 hover:bg-accent rounded-md transition-colors"
    >
        <div className="flex items-start justify-between w-full">
            <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                    <span className="font-medium text-sm">{sdk.language}</span>
                    {sdk.official && <StarIcon className="h-3 w-3 text-yellow-500 fill-current" />}
                </div>
            </div>
            <ExternalLink className="h-3 w-3 text-muted-foreground ml-2 flex-shrink-0" />
        </div>
    </div>
)

export function ButtonSDks() {
    const officialSDKs = sdkOptions.filter((sdk) => sdk.official)
    const communitySDKs = sdkOptions.filter((sdk) => !sdk.official)

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <PlugZap className="h-4 w-4 mr-2" />
                    SDK
                    <ChevronDown className="h-4 w-4 ml-2" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64 p-0">
                <Tabs defaultValue="official" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 rounded-none border-b">
                        <TabsTrigger value="official" className="text-xs">
                            Official SDKs
                        </TabsTrigger>
                        <TabsTrigger value="community" className="text-xs">
                            Community SDKs
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="official" className="mt-0 p-2">
                        <div className="space-y-1">
                            {officialSDKs.map((sdk) => (
                                <SDKItem key={sdk.id} sdk={sdk} />
                            ))}
                        </div>
                    </TabsContent>
                    <TabsContent value="community" className="mt-0 p-2">
                        <div className="space-y-1">
                            {communitySDKs.map((sdk) => (
                                <SDKItem key={sdk.id} sdk={sdk} />
                            ))}
                        </div>
                    </TabsContent>
                </Tabs>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

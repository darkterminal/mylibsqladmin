import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { CopyButton } from "@/components/ui/copy-to-clipboard"
import { SDKOption, sdkOptions } from "@/lib/sdks"
import { ExternalLink, PlugZap, StarIcon } from "lucide-react"
import { useState } from "react"

function CodeBlock({ code, label }: { code: string; label?: string }) {
    return (
        <div className="space-y-1">
            {label && (
                <div className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{label}</div>
            )}
            <pre className="max-h-64 overflow-auto rounded-md border bg-muted p-3 text-xs leading-relaxed">
                <code>{code}</code>
            </pre>
        </div>
    )
}

function SDKDetail({ sdk }: { sdk: SDKOption }) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold">{sdk.language}</h3>
                    {sdk.official ? (
                        <StarIcon className="h-4 w-4 text-yellow-500 fill-current" aria-label="official" />
                    ) : (
                        <span className="text-[10px] uppercase tracking-wide text-muted-foreground">community</span>
                    )}
                </div>
                <a
                    href={sdk.docs}
                    target="_blank"
                    rel="noreferrer noopener"
                    className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                >
                    Official docs
                    <ExternalLink className="h-3 w-3" />
                </a>
            </div>

            {sdk.installLabel && (
                <div className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                    {sdk.installLabel}
                </div>
            )}

            <div className="flex items-start gap-2">
                <pre className="max-h-28 flex-1 overflow-auto rounded-md border bg-muted p-3 text-xs leading-relaxed">
                    <code>{sdk.install}</code>
                </pre>
                <CopyButton value={sdk.install} size="icon" />
            </div>

            <div className="flex items-start gap-2">
                <div className="flex-1">
                    <CodeBlock code={sdk.snippet} label="Example" />
                </div>
                <CopyButton value={sdk.snippet} size="icon" />
            </div>

            {sdk.note && (
                <p className="text-[11px] leading-snug text-muted-foreground">{sdk.note}</p>
            )}
        </div>
    )
}

export function ButtonSDks({ databaseName }: { databaseName?: string }) {
    const officialSDKs = sdkOptions.filter((sdk) => sdk.official)
    const communitySDKs = sdkOptions.filter((sdk) => !sdk.official)
    const [selected, setSelected] = useState<string>(
        officialSDKs[0]?.id ?? sdkOptions[0].id
    )

    const selectedSDK = sdkOptions.find((sdk) => sdk.id === selected) ?? sdkOptions[0]

    const listButton = (sdk: SDKOption) => (
        <button
            key={sdk.id}
            type="button"
            onClick={() => setSelected(sdk.id)}
            aria-pressed={selected === sdk.id}
            className={`flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm transition-colors ${
                selected === sdk.id ? "bg-accent font-medium text-accent-foreground" : "hover:bg-accent/60"
            }`}
        >
            <span className="flex-1">{sdk.language}</span>
            {sdk.official ? (
                <StarIcon className="h-3 w-3 shrink-0 text-yellow-500 fill-current" />
            ) : (
                <span className="text-[9px] uppercase text-muted-foreground">community</span>
            )}
        </button>
    )

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <PlugZap className="h-4 w-4" />
                    <span className="ml-1">SDK</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto p-0 gap-0">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle className="flex items-center gap-2">
                        Connect with an SDK
                        {databaseName && (
                            <span className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs font-normal text-muted-foreground">
                                {databaseName}
                            </span>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        Minimal examples in each SDK. Full reference in the official docs (link per SDK).
                    </DialogDescription>
                </DialogHeader>

                <Tabs defaultValue="official" className="flex flex-col">
                    <div className="border-b px-6 pt-3">
                        <TabsList className="grid w-48 grid-cols-2">
                            <TabsTrigger value="official">Official</TabsTrigger>
                            <TabsTrigger value="community">Community</TabsTrigger>
                        </TabsList>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-[220px_1fr]">
                        <TabsContent value="official" className="mt-0 border-r p-2">
                            <div className="space-y-1">{officialSDKs.map(listButton)}</div>
                        </TabsContent>
                        <TabsContent value="community" className="mt-0 border-r p-2">
                            <div className="space-y-1">{communitySDKs.map(listButton)}</div>
                        </TabsContent>
                        <div className="p-5">
                            <SDKDetail sdk={selectedSDK} />
                        </div>
                    </div>
                </Tabs>

                <div className="border-t px-6 py-3 text-[11px] leading-snug text-muted-foreground">
                    Snippets cover install + connect + one query. For this stack, URL is{" "}
                    <code className="rounded bg-muted px-1 py-0.5">http://&lt;database&gt;.localhost:8080</code> (dev) or{" "}
                    <code className="rounded bg-muted px-1 py-0.5">https://&lt;database&gt;.&lt;your-domain&gt;</code> (prod),
                    with any full-access token from the Tokens page as{" "}
                    <code className="rounded bg-muted px-1 py-0.5">TURSO_AUTH_TOKEN</code> /{" "}
                    <code className="rounded bg-muted px-1 py-0.5">DB_AUTH_TOKEN</code>.
                </div>
            </DialogContent>
        </Dialog>
    )
}

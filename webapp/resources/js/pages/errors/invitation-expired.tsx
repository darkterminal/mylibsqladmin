import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader } from "@/components/ui/card"
import AuthLayout from "@/layouts/auth-layout"
import { Link } from "@inertiajs/react"
import { Clock, ExternalLink } from "lucide-react"

export default function InvitationExpiredPage() {
    return (
        <AuthLayout title="Invitation Expired" description="This team invitation link has expired or is no longer valid.">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-50">
                        <Clock className="h-10 w-10 text-red-500" />
                    </div>
                    <CardDescription className="text-base">
                        Team invitations are valid for 7 days after they are sent.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <p className="text-sm text-gray-500">
                        Ask the team owner to send you a new invitation. If you believe this is a mistake, contact support via{' '}
                        <a href="https://github.com/darkterminal/mylibsqladmin/issues" target="_blank" rel="noreferrer" className="text-primary hover:underline">
                            GitHub Issues
                        </a>.
                    </p>
                </CardContent>
                <CardFooter className="flex flex-col gap-3">
                    <Button asChild className="w-full" variant="outline">
                        <a href="https://github.com/darkterminal/mylibsqladmin/issues" target="_blank" rel="noreferrer">
                            <ExternalLink className="mr-2 h-4 w-4" />
                            Contact Support
                        </a>
                    </Button>
                    <div className="mt-2 text-center text-xs text-gray-500">
                        <Link href={route('home')} className="text-primary hover:underline">
                            Return to Home
                        </Link>
                    </div>
                </CardFooter>
            </Card>
        </AuthLayout>
    )
}

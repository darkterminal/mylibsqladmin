import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import AuthLayout from "@/layouts/auth-layout"
import { Link } from "@inertiajs/react"
import { Clock, Mail, RefreshCw } from "lucide-react"

export default function InvitationExpiredPage() {
    return (
        <AuthLayout title="Invitation Expired" description="The invitation has expired.">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-red-50">
                        <Clock className="h-10 w-10 text-red-500" />
                    </div>
                    <CardTitle className="text-2xl font-bold">Invitation Expired</CardTitle>
                    <CardDescription className="text-base">
                        This team invitation link has expired or is no longer valid.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-center">
                    <p className="text-sm text-gray-500">
                        Team invitations are valid for 7 days after they are sent. You can request a new invitation from the team
                        owner or contact support for assistance.
                    </p>
                </CardContent>
                <CardFooter className="flex flex-col gap-3">
                    <Button className="w-full" variant="default">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Request New Invitation
                    </Button>
                    <Button className="w-full" variant="outline">
                        <Mail className="mr-2 h-4 w-4" />
                        Contact Support
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

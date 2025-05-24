import { Input } from "@/components/ui/input"

export default function BasicInformationForm({
    data,
    setData,
    errors
}: {
    data: any,
    setData: any,
    errors: any
}) {
    return (
        <div className="space-y-4">
            <h3 className="text-lg font-medium">Basic Information</h3>

            <div className="space-y-2">
                <label htmlFor="name" className="text-sm font-medium">Full Name</label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={e => setData('name', e.target.value)}
                    placeholder="Enter full name"
                />
                {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                    <label htmlFor="username" className="text-sm font-medium">Username</label>
                    <Input
                        id="username"
                        value={data.username}
                        onChange={e => setData('username', e.target.value)}
                        placeholder="Enter username"
                    />
                    <p className="text-sm text-muted-foreground">This will be used for login and mentions.</p>
                    {errors.username && <p className="text-sm text-red-600">{errors.username}</p>}
                </div>

                <div className="space-y-2">
                    <label htmlFor="email" className="text-sm font-medium">Email Address</label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={e => setData('email', e.target.value)}
                        placeholder="Enter email address"
                    />
                    {errors.email && <p className="text-sm text-red-600">{errors.email}</p>}
                </div>
            </div>
        </div>
    )
}

import { Label } from "@/components/ui/label"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"

export default function UserRoleForm({
    availableRoles,
    data,
    setData,
    errors
}: {
    availableRoles: any[],
    data: any,
    setData: any,
    errors: any
}) {
    console.log(data.roleSelection)
    return (
        <div className="space-y-4 mb-3">
            <h3 className="text-lg font-medium">User Roles</h3>

            <RadioGroup
                value={data.roleSelection.toString()}
                onValueChange={(value) => setData('roleSelection', Number(value))}
                className="grid grid-cols-1 md:grid-cols-2 gap-4"
            >
                {availableRoles.map(role => (
                    <div key={role.id} className="flex items-center space-x-3 p-3 border rounded-md">
                        <RadioGroupItem
                            value={role.id.toString()}
                            id={`role-${role.id}`}
                        />
                        <Label
                            htmlFor={`role-${role.id}`}
                            className="font-medium cursor-pointer"
                        >
                            {role.name}
                        </Label>
                    </div>
                ))}
            </RadioGroup>
            {errors.roleSelection && <p className="text-sm text-red-600">{errors.roleSelection}</p>}
        </div>
    )
}

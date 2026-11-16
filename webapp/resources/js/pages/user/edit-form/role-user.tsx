import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";

/**
 * Shared role picker used by the create and edit user pages. The backend
 * validates the two forms with different field names (`roleSelection` on
 * store, `roleSelections` on update) — `fieldName` keeps this component
 * honest instead of hardcoding one.
 */
export default function UserRoleForm({
    availableRoles,
    data,
    setData,
    errors,
    fieldName = 'roleSelections',
}: {
    availableRoles: any[],
    data: any,
    setData: any,
    errors: any,
    fieldName?: string,
}) {
    const value = data[fieldName];

    return (
        <div className="space-y-4 mb-3">
            <h3 className="text-lg font-medium">User Roles</h3>

            <RadioGroup
                value={value === undefined || value === null ? '' : String(value)}
                onValueChange={(value) => setData(fieldName, Number(value))}
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
            {errors[fieldName] && <p className="text-sm text-red-600">{errors[fieldName]}</p>}
        </div>
    );
}

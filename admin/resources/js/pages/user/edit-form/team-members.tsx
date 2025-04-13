import { Checkbox } from "@/components/ui/checkbox"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

export default function TeamMembershipsForm({
    availableTeams,
    permissionLevels,
    data,
    setData,
    errors,
    handleTeamSelection
}: {
    availableTeams: any[],
    permissionLevels: any[],
    data: any,
    setData: any,
    errors: any,
    handleTeamSelection: any
}) {
    return (
        <div className="space-y-4">
            <h3 className="text-lg font-medium">Team Memberships</h3>

            <div className="space-y-4">
                {availableTeams.map(team => (
                    <div key={team.id} className="flex items-center justify-between p-4 border rounded-md">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                checked={data.teamSelections.includes(team.id)}
                                onCheckedChange={checked => handleTeamSelection(team.id, checked)}
                            />
                            <label className="font-medium cursor-pointer">{team.name}</label>
                        </div>

                        {data.teamSelections.includes(team.id) && (
                            <Select
                                value={data.teamPermissions[team.id]}
                                onValueChange={value => setData('teamPermissions', {
                                    ...data.teamPermissions,
                                    [team.id]: value
                                })}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Select permission" />
                                </SelectTrigger>
                                <SelectContent>
                                    {permissionLevels.map(level => (
                                        <SelectItem key={level.id} value={level.id}>
                                            {level.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>
                ))}
            </div>
            {errors.teamSelections && <p className="text-sm text-red-600">{errors.teamSelections}</p>}
        </div>
    )
}

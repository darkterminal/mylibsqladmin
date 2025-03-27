import { SharedData } from "@/types"
import { usePage } from "@inertiajs/react"

// Check basic permissions
export function usePermission() {
    const { auth } = usePage<SharedData>().props

    return {
        can: (permission: string) => {
            if (!auth) return false
            return auth.permissions?.abilities.includes(permission)
        },
        hasRole: (role: string) => {
            if (!auth) return false
            return auth.user.role === role
        }
    }
}

// For model-specific permissions
export function useGate() {
    const { auth } = usePage().props

    return {
        check: async (ability: string, modelType: string, modelId: number) => {
            try {
                const response = await fetch('/api/check-gate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ability,
                        model_type: modelType,
                        model_id: modelId
                    })
                })
                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();
                return data.allowed;
            } catch (error) {
                return false;
            }
        }
    }
}

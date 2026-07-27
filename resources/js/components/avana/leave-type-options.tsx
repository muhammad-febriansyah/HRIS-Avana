/**
 * A leave type as served by `LeaveType::selectableTree()`: roots in order, each
 * with its active sub-types nested underneath.
 */
export interface LeaveTypeNode {
    id: number;
    name: string;
    default_quota?: number;
    requires_attachment?: boolean;
    /** False for a branched root — it groups its sub-types rather than being a
     * choice of its own, since the days must land in one of them. */
    selectable: boolean;
    children: {
        id: number;
        name: string;
        sub_limit: number | null;
        requires_attachment?: boolean;
        selectable: boolean;
    }[];
}

/**
 * `<option>` list for a leave type `<select>`. A type without sub-types is a
 * plain option; a branched one becomes an `<optgroup>` whose children are the
 * only selectable entries, with their yearly cap shown inline.
 */
export function LeaveTypeOptions({ types }: { types: LeaveTypeNode[] }) {
    return (
        <>
            {types.map((type) =>
                type.children.length === 0 ? (
                    <option key={type.id} value={String(type.id)}>
                        {type.name}
                    </option>
                ) : (
                    <optgroup key={type.id} label={type.name}>
                        {type.children.map((child) => (
                            <option key={child.id} value={String(child.id)}>
                                {child.name}
                                {child.sub_limit !== null
                                    ? ` (maks ${child.sub_limit} hari)`
                                    : ''}
                            </option>
                        ))}
                    </optgroup>
                ),
            )}
        </>
    );
}

/**
 * Find a node by id anywhere in the tree, so a caller can read the picked
 * type's settings (e.g. whether it needs an attachment) without flattening.
 */
export function findLeaveType(
    types: LeaveTypeNode[],
    id: string | number,
): { id: number; name: string; requires_attachment?: boolean } | null {
    const target = String(id);

    for (const type of types) {
        if (String(type.id) === target) {
            return type;
        }

        for (const child of type.children) {
            if (String(child.id) === target) {
                return child;
            }
        }
    }

    return null;
}

export default LeaveTypeOptions;

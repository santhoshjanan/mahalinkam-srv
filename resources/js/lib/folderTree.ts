// Build a nested tree from the flat `folders` prop
// (`[{id, parent_id, name, position}]`).

export function buildTree(folders) {
    const byId = new Map();
    const roots = [];

    for (const f of folders) {
        byId.set(f.id, { ...f, children: [] });
    }

    for (const node of byId.values()) {
        if (node.parent_id && byId.has(node.parent_id)) {
            byId.get(node.parent_id).children.push(node);
        } else {
            roots.push(node);
        }
    }

    const sort = (list) => {
        list.sort((a, b) => a.position - b.position || a.name.localeCompare(b.name));
        list.forEach((n) => sort(n.children));
    };
    sort(roots);

    return roots;
}

// Flatten the tree to `[{id, name, depth}]` for indented <select> labels.
export function flattenForSelect(folders) {
    const out = [];
    const walk = (nodes, depth) => {
        for (const n of nodes) {
            out.push({ id: n.id, name: n.name, depth });
            walk(n.children, depth + 1);
        }
    };
    walk(buildTree(folders), 0);

    return out;
}

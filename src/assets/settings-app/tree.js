/**
 * Helpers for the field tree the server sends (see React_Renderer::describe()).
 *
 * A node is { type, name, label, ... }. Containers carry `children` (group,
 * metabox) or `tabs` ([{ id, title, fields }]); a repeater carries
 * `subFields`. Containers store nothing: their children are flat values.
 */

export const CONTAINER_TYPES = [ 'group', 'metabox', 'tabs' ];

/**
 * The child node lists of a node (one list per tab for tabs).
 *
 * @param {Object} node Node.
 * @return {Object[][]} Lists of child nodes.
 */
export function childLists( node ) {
	if ( 'tabs' === node.type ) {
		return ( node.tabs || [] ).map( ( tab ) => tab.fields || [] );
	}
	if ( 'group' === node.type || 'metabox' === node.type ) {
		return [ node.children || [] ];
	}
	return [];
}

/**
 * Every node that stores a value, in document order, descending into
 * containers but not into repeaters (a repeater stores its rows itself).
 *
 * @param {Object[]} nodes Nodes.
 * @return {Object[]} Leaf nodes.
 */
export function leafNodes( nodes ) {
	const leaves = [];

	( nodes || [] ).forEach( ( node ) => {
		if ( CONTAINER_TYPES.includes( node.type ) ) {
			childLists( node ).forEach( ( list ) =>
				leaves.push( ...leafNodes( list ) )
			);
		} else if ( 'custom_html' !== node.type ) {
			leaves.push( node );
		}
	} );

	return leaves;
}

/**
 * The tab ancestors of a field: [{ tabs: <tabs field name>, tab: <tab id> }].
 *
 * @param {Object[]} nodes Nodes.
 * @param {string}   name  Field name.
 * @return {Object[]|null} Ancestors (outermost first), or null when not found.
 */
export function tabPath( nodes, name ) {
	for ( const node of nodes || [] ) {
		if ( 'tabs' === node.type ) {
			for ( const tab of node.tabs || [] ) {
				const found = tabPath( tab.fields, name );
				if ( found ) {
					return [ { tabs: node.name, tab: tab.id }, ...found ];
				}
			}
		} else if ( CONTAINER_TYPES.includes( node.type ) ) {
			const found = tabPath( node.children, name );
			if ( found ) {
				return found;
			}
		} else if ( node.name === name ) {
			return [];
		}
	}
	return null;
}

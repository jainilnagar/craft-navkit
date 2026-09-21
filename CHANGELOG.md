# Release Notes for Navkit

## 1.0.1 - 2026-09-21

### Fixed
- The "New node" button now targets the currently-selected menu source instead of always the first menu.

## 1.0.0

Initial release.

### Menus & nodes
- Menu definitions stored in project config (deployable across environments), each backed by a Craft Structure.
- Node custom element type: structured (drag-and-drop nesting with a per-menu max-depth cap), multi-site, translation-ready, with statuses. Disabling a node removes its whole subtree from the front end.

### Links
- Built-in link types: URL, Entry, Category, Asset, Commerce Product (auto-hidden without Commerce), and Passive (no link).
- Element links resolve their URL live at render time, so a linked element's slug/URI change flows through automatically.
- `LinkTypes::EVENT_REGISTER_LINK_TYPES` for registering custom link types.

### Authoring
- Per-menu custom field layouts for nodes, via the field-layout designer on the menu editor.
- Link editor (type selector, per-type target, open-in-new-tab, CSS classes, rel) built into every node.
- Per-menu permissions (`navkit:manageMenus`, `navkit:manageNodes`).

### Front end
- `craft.navkit.render('handle')` outputs a complete nested `<nav>` with active-trail detection, ARIA attributes, depth limiting, and overridable templates.
- `craft.navkit.nodes()` raw query factory for custom markup.
- Rendered trees are cached per menu + site with automatic, element-aware invalidation.

### GraphQL
- `navkitMenu(handle: "...")` query returns a menu as a nested tree with live-resolved URLs.

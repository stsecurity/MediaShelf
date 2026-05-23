# MediaShelf

MediaShelf is a Typecho plugin for building a personal media shelf for anime, manga, games, music, novels, and visual novels.

It adds admin pages for managing works, optional provider imports, a public `/works` page, and editor shortcodes for placing MediaShelf sections inside normal Typecho pages or posts.

## Install

Copy this folder to your Typecho plugin directory:

```text
usr/plugins/MediaShelf
```

The folder name must be `MediaShelf`.

## Enable

In Typecho admin:

1. Open **Console > Plugins**.
2. Enable **MediaShelf**.
3. Open **Media Shelf > Works** to add works manually.
4. Optional: open the plugin settings to change the public slug, display fields, title text, and import provider options.

Disabling the plugin keeps its database table and user data.

## Public Works Page

By default, the plugin registers:

```text
/works
```

Only works with `Published` status appear publicly. Draft and hidden works stay admin-only.

The route slug can be changed in the plugin settings.

## Editor Shortcodes

Use these in a normal Typecho post or page:

```text
[mediashelf_title]

[mediashelf_search]

[mediashelf_cards]
```

Or use the combined shortcode:

```text
[mediashelf]
```

If shortcodes do not render after updating an already-active install, open any MediaShelf admin page or the plugin settings once. MediaShelf will repair the saved Typecho content hook automatically.

## Imports

External imports are admin-only helper flows. Imported works are saved as editable drafts so all fields can be reviewed before publishing.

Supported provider structure includes AniList, Open Library, MusicBrainz, Steam, RAWG, IGDB, and VNDB. Providers that require API keys use server-side settings only.

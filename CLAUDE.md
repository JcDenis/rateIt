# rateIt

Notes for AI assistants working on the Dotclear plugin rateIt.

**Read first** the shared conventions of the author's Dotclear modules:
https://raw.githubusercontent.com/JcDenis/gh2da/master/CONVENTIONS.md
(if a web tool refuses the address, use `curl -s` on it from the shell).
The rules below take precedence for this module.

## Module

Rating system for your blogs: entries, comments, categories, tags, events (plugin eventHandler) and links (plugin cinecturlink2).

Admin page and configuration page. Public side: widgets, URL handlers.

Required Dotclear and PHP versions: see `_define.php`.

## Structure

* `src/ClaudetteMcp/ListRatings.php`: tool `list_ratings` for the plugin ClaudetteMcp (read only, never required),
  registered in `src/Frontend.php` with the behavior `ClaudetteMcpAddTool`. Use `Module::item()`, not `adminItem()`:
  admin URLs throw out of backend.
* `src/ActivityReportAction.php`: add rating actions to the plugin activity report.
* `src/Backend.php`: admin side: menu entry, favorites, behaviors registration.
* `src/Config.php`: configuration page of the module (plugins manager).
* `src/Frontend.php`: public side: behaviors, template tags and URL handlers registration.
* `src/FrontendBehaviors.php`: public behaviors.
* `src/FrontendTemplate.php`: template tags.
* `src/FrontendUrl.php`: public URL handler.
* `src/Install.php`: install and update, database structure (a change needs a migration here).
* `src/Manage.php`: admin page of the module.
* `src/Module.php`: a module defines a type of rated items.
* `src/My.php`: module helpers (`My::id()`, settings, permissions).
* `src/Prepend.php`: loaded on every request: autoload, URL handlers, permission types.
* `src/PrependBehaviors.php`: behaviors.
* `src/Rates.php`: votes are stored in table rateit.
* `src/Render.php`: keeps the stack of rated items for template blocks.
* `src/Uninstall.php`: cleaners for the plugin Uninstaller.
* `src/Widgets.php`: widgets definition and rendering.
* `src/Module/Category.php`: categories module.
* `src/Module/Cinecturlink2.php`: links module (plugin cinecturlink2).
* `src/Module/Comment.php`: comments module.
* `src/Module/EventHandler.php`: events module (plugin eventHandler).
* `src/Module/Post.php`: entries module.
* `src/Module/PostType.php`: module base for entries post types.
* `src/Module/Tag.php`: tags module (plugin tags).
* `js/`: `frontend.js`.
* `default-templates/`: public templates.
* Database table `rateit`.
* URL handlers: `rateit_vote`.
* Widgets: `rateit`, `rateitrank`.
* Behaviors used: `ClaudetteMcpAddTool`, `initWidgets`, `adminDashboardFavoritesV2`, `publicBeforeDocumentV2`, `publicHeadContent`, `publicEntryAfterContent`, `publicCommentAfterContent`, `adminBeforePostDelete`, `adminBeforePostsDelete`, `adminBeforeCommentDelete`, `adminBeforeCommentsDelete`.

## Pitfalls

* Table structure changes need a migration in `src/Install.php` (version compare), and must work on MySQL, PostgreSQL and SQLite.

## Testing

* Check the admin page, the configuration page, the widgets (admin and public), the public URLs, the admin help, in English and French.
* CI browses the common admin and public pages; extra pages can be listed in `.github/dotclear-test.txt`.

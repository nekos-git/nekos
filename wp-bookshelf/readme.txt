=== UZ Bookshelf ===
Contributors: uzmedia
Tags: bookshelf, books, 3d, affiliate, rakuten
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your book collection on a stunning 3D CSS bookshelf with Rakuten Books integration and affiliate link support.

== Description ==

UZ Bookshelf transforms your WordPress site into an interactive literary showcase. Embed beautiful, wooden-textured 3D bookshelves on any page using a simple shortcode. Books are rendered with realistic CSS 3D transforms and organized by format — bunko, tankobon, shinsho, comic, and more.

**Key Features:**

* **3D CSS Bookshelf Visualization** — Realistic wooden shelves with perspective-correct book spines rendered entirely in CSS
* **Multiple Book Formats** — Supports bunko, tankobon, shinsho, comic, oversized, and other Japanese book formats with accurate sizing
* **Rakuten Books API Integration** — Search and import book data directly from Rakuten Books
* **Affiliate Link Support** — Built-in support for both Amazon and Rakuten affiliate links to monetize your recommendations
* **Article & Essay System** — Write long-form book reviews and essays, with automatic import from Movable Type exports
* **Critique Theme Taxonomy** — Organize articles by cross-genre themes for thematic discovery beyond traditional categories
* **SEO Optimized** — OGP meta tags, JSON-LD structured data, XML sitemap support, and URL routing for bookmarkable views
* **Shortcode Embedding** — Drop `[uz_bookshelf]` on any page or post with options to filter by mode, shelf, or related article
* **REST API Powered** — Fully decoupled frontend powered by the WordPress REST API and a React-based interface
* **Individual Book & Theme Pages** — Dedicated pages for each book and each critique theme with clean URLs

**Shortcode Options:**

* `[uz_bookshelf]` — Display the full bookshelf with all modes
* `[uz_bookshelf mode="uz"]` — Show UZ Selection books only
* `[uz_bookshelf mode="rakuten"]` — Show Rakuten Books results only
* `[uz_bookshelf shelf="books"]` — Display a specific shelf
* `[uz_bookshelf article="article-id"]` — Show books related to a specific article

== Installation ==

1. Upload the `wp-bookshelf` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Create a new page and add the `[uz_bookshelf]` shortcode to display your bookshelf.
4. Go to the UZ Bookshelf admin menu to add books and configure affiliate settings.

== Frequently Asked Questions ==

= How do I add books? =

You can add books manually through the UZ Bookshelf admin page, or search and import them directly from the Rakuten Books API. Each book can be assigned a format, shelf position, and affiliate links.

= What shortcode options are available? =

The `[uz_bookshelf]` shortcode accepts four optional attributes: `mode` (filter by "uz" or "rakuten"), `shelf` (show a specific shelf), `article` (show books related to an article), and `height` (override the container height).

= What book formats are supported? =

The plugin supports Japanese book formats including bunko, tankobon, shinsho, comic, and oversized sizes. Each format renders with accurate proportions on the 3D shelf.

= Can I use affiliate links? =

Yes. UZ Bookshelf supports both Amazon and Rakuten affiliate links. You can configure affiliate IDs in the plugin settings and they will be automatically applied to book links.

= What are critique themes? =

Critique themes are a custom taxonomy that lets you organize articles across genres by shared thematic ideas — for example, grouping reviews of a novel and a manga under a common literary theme. This enables cross-genre discovery for your readers.

== Screenshots ==

1. 3D bookshelf display with realistic wooden shelves and CSS-rendered book spines.
2. Book detail modal showing cover, metadata, and affiliate links.
3. Article list view with card grid layout and critique theme filtering.

== Changelog ==

= 1.3.0 =
* Added SEO support with OGP meta tags and JSON-LD structured data
* Added URL routing for bookmarkable views
* Added individual book pages
* Added theme landing pages
* Added XML sitemap support
* Improved affiliate link handling
* Security improvements

= 1.2.0 =
* Added critique theme taxonomy for cross-genre article discovery
* Added article import from Movable Type export files
* Added article detail view with full body content

= 1.1.0 =
* Unified database schema (v2)
* Added Rakuten Books API search and import
* Card grid layout for articles

= 1.0.0 =
* Initial release
* 3D bookshelf visualization with CSS transforms
* WordPress REST API integration
* Rakuten Books integration

== Upgrade Notice ==

= 1.3.0 =
Major update with SEO improvements including structured data, sitemaps, and bookmarkable URLs. Recommended for all users.

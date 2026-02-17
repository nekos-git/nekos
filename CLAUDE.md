# CLAUDE.md

## Project Overview

**nekos** is a browser-based Augmented Reality (AR) web application using A-Frame and AR.js. It displays an image overlay when a custom AR marker pattern is detected through a device camera. The project is a lightweight proof-of-concept with no build system or dependencies beyond CDN-hosted libraries.

## Repository Structure

```
nekos/
├── README.md              # Project title only
├── CLAUDE.md              # This file
└── test/                  # AR demo application and assets
    ├── index.html         # Main AR application (A-Frame + AR.js)
    ├── 1.png              # Image displayed in AR overlay
    ├── pattern-tjh.patt   # Custom AR marker pattern file (ASCII)
    ├── pattern-tjh.png    # Visual representation of the AR marker
    └── LICENSE            # MIT + SIL Open Font License + M+ Fonts license
```

## Tech Stack

- **HTML5** — Static markup, no custom JavaScript
- **A-Frame 1.0.4** — WebGL/3D framework built on Three.js (loaded via CDN)
- **AR.js 1.5.5** — Browser-based AR library for marker tracking (loaded via CDN)

All dependencies are loaded from external CDNs (`aframe.io`, `cdn.rawgit.com`). There are no local dependencies, no `package.json`, and no package manager.

## Architecture

The entire application is a single HTML file (`test/index.html`) that uses A-Frame's declarative entity-component-system (ECS) pattern:

- `<a-scene>` — Root AR scene with AR.js configuration
- `<a-marker>` — Defines the custom pattern marker (`pattern-tjh.patt`)
- `<a-entity>` — Positions the 3D content relative to the marker
- `<a-image>` — Renders `1.png` as a 2D image in 3D space

HTML comments are in Japanese (the project originates from a Japanese developer).

## Development

### Running Locally

Serve the `test/` directory with any static HTTP server. A camera/webcam is required for AR marker detection.

```sh
# Example using Python
python3 -m http.server 8000 --directory test/

# Example using Node.js http-server
npx http-server test/
```

Then open `http://localhost:8000` in a WebGL-capable browser and point the camera at the printed marker pattern.

### Build System

None. This is a static HTML project with no build, transpilation, bundling, or minification steps.

### Testing

No automated tests exist. Manual testing is done by:
1. Serving the HTML file locally
2. Pointing a webcam at the `pattern-tjh.png` marker
3. Verifying the image overlay appears correctly

### Linting / Formatting

No linters or formatters are configured.

## Git Conventions

- **Default branch:** `main` (remote) / `master` (local)
- **Commit style:** Lowercase, concise messages (e.g., "first uploads")
- No conventional commits format is enforced

## Key Files for Modification

| File | Purpose |
|---|---|
| `test/index.html` | Main application — edit to change AR behavior, scene content, or library versions |
| `test/pattern-tjh.patt` | AR marker pattern — replace to use a different marker |
| `test/1.png` | Overlay image — replace to change what is displayed in AR |

## Notes for AI Assistants

- This is a minimal, static project. Do not introduce build tooling, package managers, or frameworks unless explicitly requested.
- CDN URLs use older versions of A-Frame (1.0.4) and AR.js (1.5.5). The `cdn.rawgit.com` host is deprecated; if updating dependencies, migrate to a current CDN (e.g., jsDelivr, unpkg).
- The `test/` directory name is a misnomer — it contains the actual application, not test suites.
- All asset licenses are documented in `test/LICENSE` (MIT, SIL OFL 1.1, M+ Fonts).

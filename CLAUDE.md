# CLAUDE.md

## Project Overview

**nekos** is a static web-based Augmented Reality (AR) demo project built with [A-Frame](https://aframe.io/) and [AR.js](https://ar-js-org.github.io/AR.js-Docs/). It uses marker-based AR detection to overlay an image in 3D space when a custom pattern is recognized by the device camera.

- **License:** MIT (code) + SIL Open Font License (fonts) + M+ Fonts license
- **Author:** studioTeaTwo / Toshiya Tanaka (2020)

## Repository Structure

```
nekos/
├── CLAUDE.md              # This file — AI assistant guide
├── README.md              # Project readme (minimal)
└── test/                  # Main application directory
    ├── index.html         # AR scene entry point (A-Frame + AR.js)
    ├── 1.png              # Image asset displayed in the AR scene
    ├── pattern-tjh.patt   # Custom AR marker pattern (ASCII grayscale)
    ├── pattern-tjh.png    # Visual representation of the AR marker
    └── LICENSE            # MIT + SIL OFL + M+ Fonts licenses
```

## Technology Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| A-Frame    | 1.0.4   | WebXR/3D scene framework (loaded via CDN) |
| AR.js      | 1.5.5   | Marker-based AR for the web (loaded via CDN) |
| HTML       | 5       | Application markup |

All dependencies are loaded from CDNs — there is no package manager, no `node_modules`, and no build step.

## How It Works

1. `test/index.html` loads A-Frame and AR.js from CDNs
2. An `<a-scene>` is created with AR.js configuration (debug UI disabled, best tracking method)
3. A custom marker (`pattern-tjh.patt`) is registered for detection
4. When the marker is detected through the device camera, `1.png` is rendered as a 3D image entity at a fixed position and rotation

## Development Workflow

### Running Locally

Since this is a static HTML project, serve the `test/` directory with any HTTP server:

```bash
# Using Python
python3 -m http.server -d test 8080

# Using Node.js (npx)
npx serve test
```

Then open `http://localhost:8080` in a browser with camera access. Point the camera at the printed `pattern-tjh.png` marker to see the AR overlay.

### No Build, Test, or Lint Commands

This project has:
- **No build system** — no bundler, no compiler, no transpilation
- **No test framework** — no unit or integration tests
- **No linting/formatting** — no ESLint, Prettier, or similar tooling
- **No CI/CD** — no GitHub Actions, no pipelines
- **No package manager** — no `package.json`, no `node_modules`

### Making Changes

- Edit `test/index.html` directly to modify the AR scene
- Replace `test/1.png` to change the displayed image (update `width`/`height` attributes in the HTML to match new dimensions)
- Generate a new `.patt` file using an [AR.js marker trainer](https://jeromeetienne.github.io/AR.js/three.js/examples/marker-training/examples/generator.html) to change the detection marker

## Key Conventions

- Comments in `index.html` are written in Japanese (日本語)
- The marker pattern file (`pattern-tjh.patt`) is an ASCII-encoded grayscale matrix used by AR.js for marker detection
- Image dimensions in `<a-image>` (`width="6.14" height="2.77"`) correspond to the actual pixel ratio of `1.png` (614×277)

## Notes for AI Assistants

- This is a minimal demo project — avoid adding unnecessary complexity (build tools, frameworks, package managers) unless explicitly requested
- CDN URLs are pinned to specific versions; changing them may break compatibility
- The `test/` directory name is a misnomer — it contains the actual application, not test suites
- AR.js requires HTTPS or localhost for camera access in modern browsers
- The `pattern-tjh.patt` file must remain in sync with `pattern-tjh.png` — regenerate both together if the marker needs to change

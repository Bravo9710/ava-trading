# AvaTrade Testimonials — Headless WordPress + Next.js

A "We Let Our Clients Do The Talking" testimonials section, powered by a custom
WordPress plugin (headless CMS) and rendered by a Next.js front end.

- **`wordpress-plugins/`** — the `avatrade-testimonials` WordPress plugin (CPT, custom
  fields, REST output, demo-data seeding).
- **`web/`** — the Next.js (App Router + TypeScript) app that consumes the REST API and
  renders the carousel.
- **`avatrade-testimonials.zip`** — a ready-to-install build of the plugin.

---

## Repository layout

```
ava-trading/
├── wordpress-plugins/
│   ├── avatrade-testimonials.php        # the plugin (single bootstrap file)
│   ├── index.php                        # "silence is golden" guard
│   └── assets/seed-images/              # 6 bundled demo author photos (1.jpg … 6.jpg)
├── web/                                 # Next.js app
│   ├── src/app/
│   │   ├── page.tsx                     # Server Component — fetches + renders the section
│   │   ├── layout.tsx
│   │   ├── globals.scss                 # global styles (SCSS)
│   │   └── components/
│   │       ├── TestimonialsSlider.tsx   # "use client" Swiper carousel
│   │       └── TestimonialBox.tsx       # presentational card
│   ├── src/lib/wp.ts                    # WordPress REST data layer
│   └── next.config.ts
├── avatrade-testimonials.zip            # installable plugin build
└── README.md
```

---

## Prerequisites

- **Node.js 20+** and npm (built with Node 26 / npm 11).
- A **WordPress 6.x** site with **PHP 7.4+**. This project was developed against
  [LocalWP](https://localwp.com/) at `http://ava-trading-testimonials.local`.

---

## WordPress plugin

### Install

**Option A — upload the build (easiest):**

1. WP Admin → **Plugins → Add New Plugin → Upload Plugin**.
2. Choose `avatrade-testimonials.zip` (repo root) → **Install Now** → **Activate**.

**Option B — copy the source:**
Copy the plugin into your install as a folder named `avatrade-testimonials`:

```
wp-content/plugins/avatrade-testimonials/
├── avatrade-testimonials.php
├── index.php
└── assets/seed-images/1.jpg … 6.jpg
```

then activate it from the Plugins screen.

### What activation does

On first activation the plugin **seeds 6 demo testimonials** (name, headline, quote,
1–5 rating, source) and **sideloads a bundled photo** for each as the featured image — so
the front end renders out of the box with **no manual setup**. Seeding is one-time, guarded
by the `avatrade_testimonials_seeded` option, and wrapped in `try/catch` so a seeding hiccup
can never block activation.

> To re-seed: delete the testimonials **and** the `avatrade_testimonials_seeded` option, then
> deactivate/reactivate.

### Verify the REST endpoint

Make sure **Settings → Permalinks** is _not_ "Plain" (so pretty `/wp-json/` routes work), then:

```
GET http://ava-trading-testimonials.local/wp-json/wp/v2/avatrade_testimonial
```

should return 6 testimonials.

### Rebuilding the zip (after editing the source)

```bash
cd wordpress-plugins
mkdir -p .build/avatrade-testimonials
cp avatrade-testimonials.php index.php .build/avatrade-testimonials/
cp -R assets .build/avatrade-testimonials/
( cd .build && zip -X -r ../../avatrade-testimonials.zip avatrade-testimonials )
rm -rf .build
```

---

## Next.js app

```bash
cd web
npm install
cp .env.local.example .env.local   # or create .env.local (see below)
npm run dev                          # http://localhost:3000
```

`npm run build` produces a production build; the home page is statically generated with ISR.

---

## Connecting the two

The app reads a single **server-side** environment variable. Create `web/.env.local`:

```bash
# Base URL of the headless WordPress site (no trailing slash, no NEXT_PUBLIC_).
WORDPRESS_API_URL=http://ava-trading-testimonials.local
```

`src/lib/wp.ts` fetches `${WORDPRESS_API_URL}/wp-json/wp/v2/avatrade_testimonial`. Because
the fetch runs on the server, the WordPress URL is never exposed to the browser and there
are no cross-origin issues.

> **Local-dev note:** LocalWP serves on a loopback IP, and Next 16's image optimizer blocks
> private IPs by default (SSRF protection). `next.config.ts` sets
> `images.dangerouslyAllowLocalIP: true` for this reason — it's a no-op against a public
> production domain.

---

## WordPress content structure (and why)

A single custom post type, **`avatrade_testimonial`** (`show_in_rest: true`), with this
field mapping:

| Field                                    | Stored as                   | REST location                          |
| ---------------------------------------- | --------------------------- | -------------------------------------- |
| Headline (e.g. "Gives me peace of mind") | **post title**              | `title.rendered`                       |
| Author name                              | meta `avatrade_author_name` | `meta.avatrade_author_name`            |
| Quote body                               | meta `avatrade_quote`       | `meta.avatrade_quote`                  |
| Rating (1–5)                             | meta `avatrade_rating`      | `meta.avatrade_rating`                 |
| Source / platform                        | meta `avatrade_source`      | `meta.avatrade_source`                 |
| Author photo                             | **featured image**          | custom field `avatrade_featured_image` |

Resulting payload per item:

```jsonc
{
	"id": 6,
	"title": { "rendered": "Gives me peace of mind" },
	"meta": {
		"avatrade_author_name": "Sarah Mitchell",
		"avatrade_quote": "…",
		"avatrade_rating": 5,
		"avatrade_source": "Trustpilot",
	},
	"avatrade_featured_image": {
		"url": "…/1.jpg",
		"width": 612,
		"height": 408,
		"alt": "Sarah Mitchell",
	},
}
```

**Why this shape:**

- **Custom Post Type** is the idiomatic WordPress way to model a repeatable content entity,
  and `show_in_rest` publishes it to `/wp-json/wp/v2/…` automatically.
- **Native meta boxes** (not ACF or a Gutenberg block) for the editor UI. The brief required
  the plugin to work with _no setup beyond install + activate_ — ACF would force editors to
  install/configure a second plugin (and free ACF doesn't fully expose to REST), and a
  Gutenberg block over-engineers a fixed set of fields and complicates headless consumption.
  Core APIs keep the plugin fully self-contained and demonstrate the REST ecosystem directly.
- The post type intentionally **does not support `editor`**, so it uses the classic edit
  screen — the meta box is the single write path, avoiding any block-editor/meta-box
  double-save reconciliation.
- **`custom-fields` support is required** for `register_post_meta(show_in_rest)` to actually
  appear under `meta` in REST — a subtle WordPress requirement the plugin opts into (and the
  redundant default "Custom Fields" box is hidden to keep the editor clean).
- A **custom `avatrade_featured_image` REST field** resolves the featured image to a
  ready-to-use `{ url, width, height, alt }` object, so the front end gets the URL **and**
  dimensions in one request instead of chasing `?_embed` / `_embedded`.
- Everything is namespaced with a unique **`avatrade_` / `AVATRADE_TESTIMONIALS_`** prefix.

---

## Architecture decisions

- **Next.js App Router** (not Pages Router). React Server Components let the WordPress fetch
  run on the server with ISR caching; `loading.tsx`/`error.tsx` conventions map cleanly to the
  required states; smaller client bundle.
- **Server/client split.** `page.tsx` is a Server Component that fetches + maps the data and
  passes a typed `Testimonial[]` to `TestimonialsSlider` (`"use client"`, owns Swiper).
  `TestimonialBox` is a pure presentational card. Only the interactive carousel ships JS.
- **Typed data layer (`src/lib/wp.ts`).** A single `getTestimonials()` fetches the REST
  collection and maps the raw WordPress shape into a clean domain model
  (`Testimonial`), decoding title entities. UI components never touch WordPress's shape.
- **Styling: SCSS** via a single global stylesheet (`globals.scss`) with plain class names.
  For one self-contained section this is simpler to reason about than CSS Modules, and SCSS
  nesting keeps the slider/card rules organized. `sass` is the only styling dependency.
- **Carousel: Swiper** (React, with the `Navigation` + `Pagination` modules). Mature,
  accessible, supports mouse drag, keyboard, loop and centered slides natively. The signature
  "center card focused, neighbours scaled + blurred" stack is done with a small
  `updateLayers` helper that tags each slide with its distance from center
  (`data-layer` / `data-side`), which CSS turns into per-layer `transform: scale()/translateX()`
  - blur. Crucially the overlap uses **transforms, not margins**, so Swiper's layout math
    stays intact (fixed-width slides + `slidesPerView="auto"` + `centeredSlides`).

---

## Performance decisions

- **Server-side fetch + ISR** (`fetch(…, { next: { revalidate: 60 } })`) — the page is
  statically generated and revalidated every 60s, so visitors get cached HTML and WordPress
  isn't hit per request.
- **Trimmed payload** via `?_fields=id,title,meta,avatrade_featured_image`.
- **`next/image`** for the author photos (optimized, responsive, lazy), with the upstream host
  whitelisted in `remotePatterns`; avatars use `fill` + `object-fit: cover` so any source
  aspect ratio crops cleanly.
- **GPU-friendly animation.** The carousel animates only `transform` and `filter: blur()`
  (with `will-change`), the static `drop-shadow` glow lives on the card (off the animated
  filter), and Swiper's `speed` is matched to the CSS transition duration/easing so the slide,
  scale and blur move as one motion.

---

## Accessibility

- Semantic markup (`blockquote` for quotes), star rating exposed via `role="img"` +
  `aria-label="Rated N out of 5"`, decorative icons hidden from assistive tech.
- Swiper provides keyboard navigation and mouse-drag; the custom pagination dots are real
  focusable `<button role="tab">`s with `aria-selected` and per-dot `aria-label`s.
- **`prefers-reduced-motion`.** Users who request reduced motion get the slide/scale/blur
  transitions disabled (the carousel still works — layers snap instead of animating).

---

## Known limitations / what I'd improve with more time

- **WordPress hosting.** I first tried a hosted **Wasmer** WordPress, but its
  serverless/ephemeral model didn't reliably persist a custom plugin to the instances serving
  public REST, so I moved to **LocalWP**. For production I'd deploy the plugin baked into the
  WP image (or a managed WP host) rather than via dashboard upload.
- **Loading/error UI.** `getTestimonials()` throws on failure; I'd add `loading.tsx` and
  `error.tsx` boundaries plus an empty state.
- **Tests.** No automated tests yet — I'd add unit tests for the `wp.ts` mapper and a
  Playwright smoke test for the carousel.

---

**Where AI helped:**

- Scaffolding the Next.js app and the plugin bootstrap; writing the CPT/meta/meta-box/REST and
  seeder PHP; the typed `wp.ts` data layer;
- Debugging several real issues end-to-end: the `custom-fields`-support requirement for REST
  meta; Next 16's private-IP image block (`dangerouslyAllowLocalIP`);
- Drafting this README.

**Human-owned decisions and review:**

- Product/content-model choices (e.g. mapping the post title to the headline and author name to
  meta), the visual design and all CSS tuning (overlap, scale, blur, clip-path, colours), the
  choice of LocalWP, and final review/QA of every change in the browser.
- Decided to use slider library Swiper, based on its popularity and features.
- Review of each commit before pushing to git.

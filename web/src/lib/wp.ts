/**
 * WordPress REST data layer for testimonials.
 *
 * Runs on the server: fetches from the headless WordPress site (WORDPRESS_API_URL)
 * and maps the raw REST payload into a clean, frontend-friendly model so UI
 * components never deal with WordPress's shape directly.
 */

export interface TestimonialImage {
  url: string;
  width: number;
  height: number;
  alt: string;
}

export interface Testimonial {
  id: number;
  /** Short headline — the WordPress post title (e.g. "Gives me peace of mind"). */
  headline: string;
  /** Client name — meta.avatrade_author_name. */
  authorName: string;
  /** Full testimonial text — meta.avatrade_quote. */
  quote: string;
  /** Star rating, 1–5 — meta.avatrade_rating. */
  rating: number;
  /** Review source / platform — meta.avatrade_source (e.g. "Trustpilot"). */
  source: string;
  /** Author photo, or null when none is set. */
  image: TestimonialImage | null;
}

/** The subset of the WordPress REST response we rely on. */
interface WpTestimonial {
  id: number;
  title: { rendered: string };
  meta: {
    avatrade_author_name?: string;
    avatrade_quote?: string;
    avatrade_rating?: number;
    avatrade_source?: string;
  };
  avatrade_featured_image: TestimonialImage | null;
}

/** Decode the handful of HTML entities WordPress emits in title.rendered. */
function decodeEntities(text: string): string {
  return text
    .replace(/&#8217;/g, "’")
    .replace(/&#8216;/g, "‘")
    .replace(/&#8220;/g, "“")
    .replace(/&#8221;/g, "”")
    .replace(/&#038;|&amp;/g, "&")
    .replace(/&#039;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">");
}

function mapTestimonial(raw: WpTestimonial): Testimonial {
  const authorName = raw.meta?.avatrade_author_name ?? "";

  return {
    id: raw.id,
    headline: decodeEntities(raw.title?.rendered ?? ""),
    authorName,
    quote: raw.meta?.avatrade_quote ?? "",
    rating: Number(raw.meta?.avatrade_rating ?? 0),
    source: raw.meta?.avatrade_source ?? "",
    image: raw.avatrade_featured_image
      ? {
          ...raw.avatrade_featured_image,
          alt: raw.avatrade_featured_image.alt || authorName,
        }
      : null,
  };
}

/**
 * Fetch all testimonials from WordPress, mapped to the domain model.
 *
 * Cached with ISR (revalidate every 60s). Throws on misconfiguration or a
 * non-OK response so an error boundary can render a fallback.
 */
export async function getTestimonials(): Promise<Testimonial[]> {
  const base = process.env.WORDPRESS_API_URL?.replace(/\/$/, "");
  if (!base) {
    throw new Error("WORDPRESS_API_URL environment variable is not set.");
  }

  const endpoint = `${base}/wp-json/wp/v2/avatrade_testimonial?per_page=20&_fields=id,title,meta,avatrade_featured_image`;

  const res = await fetch(endpoint, { next: { revalidate: 60 } });
  if (!res.ok) {
    throw new Error(`WordPress request failed: ${res.status} ${res.statusText}`);
  }

  const data = (await res.json()) as WpTestimonial[];
  return data.map(mapTestimonial);
}

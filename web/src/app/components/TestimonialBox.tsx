import Image from "next/image";
import type { Testimonial } from "@/lib/wp";

interface TestimonialBoxProps {
  testimonial: Testimonial;
}

export default function TestimonialBox({ testimonial }: TestimonialBoxProps) {
  const { headline, authorName, quote, rating, source, image } = testimonial;
  const stars = Math.max(0, Math.min(5, Math.round(rating)));

  return (
    <div className="testimonial-box">
      <Image src="/quotes-icon.png" alt="quotes icon" width={25} height={24} loading="eager" />

      <div className="testimonial-rating" role="img" aria-label={`Rated ${stars} out of 5`}>
        {"★".repeat(stars)}
        {"☆".repeat(5 - stars)}
      </div>

      {headline && <p className="testimonial-headline">{headline}</p>}

      <blockquote className="testimonial-quote">{quote}</blockquote>

      <div className="testimonial-author">
        {image && (
          <span className="testimonial-avatar">
            <Image src={image.url} alt={image.alt} fill sizes="56px" />
          </span>
        )}
        <span className="testimonial-name">{authorName}</span>
        {source && <span className="testimonial-source">via {source}</span>}
      </div>
    </div>
  );
}

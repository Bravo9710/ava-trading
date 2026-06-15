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

      <div className="testimonial-box-content">
        {image && (
          <span className="testimonial-avatar">
            <Image src={image.url} alt={image.alt} fill sizes="56px" />
          </span>
        )}
        {/* {source && <span className="testimonial-source">via {source}</span>} */}
        <div className="testimonial-body">
          <div>
            {headline && <h4 className="testimonial-headline">{headline}</h4>}
            <blockquote className="testimonial-quote">{quote}</blockquote>
            <span className="testimonial-name">{authorName}</span>
          </div>
          <div className="testimonial-rating" role="img" aria-label={`Rated ${stars} out of 5`}>
            <Image src="/trustpilot-logo.svg" alt="trustpilot icon" width={117} height={24} loading="eager" />
            <div className="testimonial-stars">
              {Array.from({ length: 5 }, (_, i) => (
                <div key={i} className="star-box">
                  {i >= 5 - stars && (
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="white" aria-hidden="true">
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

"use client";

import { useRef, useState } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";
import type { Swiper as SwiperType } from "swiper";
import "swiper/css";
import "swiper/css/navigation";
import TestimonialBox from "./TestimonialBox";
import type { Testimonial } from "@/lib/wp";

interface TestimonialsSliderProps {
  testimonials: Testimonial[];
}

/**
 * A centred loop has to keep enough slides on BOTH sides of the active one to
 * fill the viewport. With only a handful of testimonials Swiper runs out of
 * slides to wrap around and leaves one side short (e.g. the +2 card never
 * appears on the right). So we repeat the set until there are comfortably
 * enough slides, and drive our own pagination off the real index so the dot
 * count still matches the number of *unique* testimonials.
 */
const MIN_SLIDES_FOR_LOOP = 10;

/**
 * Tag each slide with its distance from the centered/active one so CSS can
 * layer it (scale + blur + z-index).
 *
 * Distance is derived from each slide's actual layout position (`offsetLeft`),
 * NOT its index in `swiper.slides`. In loop mode Swiper repositions/recycles
 * slides, so the array index can lag the real visual order. The layout offset
 * always reflects where a slide truly sits relative to the active.
 *   layer 2 = active · layer 1 = adjacent · layer 0 = outer · hidden = beyond ±2
 */
function updateLayers(swiper: SwiperType) {
  const slides = swiper.slides as HTMLElement[];
  const active = slides[swiper.activeIndex];
  if (!active) return;

  const width = active.offsetWidth || 1;
  const activeLeft = active.offsetLeft;

  slides.forEach((slide) => {
    const dist = Math.round((slide.offsetLeft - activeLeft) / width);
    const absDist = Math.abs(dist);
    const side = dist < 0 ? "left" : "right";

    if (absDist === 0) {
      slide.dataset.layer = "2"; // active (centred)
      delete slide.dataset.side;
    } else if (absDist === 1) {
      slide.dataset.layer = "1"; // adjacent
      slide.dataset.side = side;
    } else if (absDist === 2) {
      slide.dataset.layer = "0"; // outer
      slide.dataset.side = side;
    } else {
      slide.dataset.layer = "hidden"; // beyond ±2 — only 5 cards ever show
      slide.dataset.side = side;
    }
  });
}

export default function TestimonialsSlider({ testimonials }: TestimonialsSliderProps) {
  const swiperRef = useRef<SwiperType | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);

  if (testimonials.length === 0) return null;

  // Repeat the testimonials so the centred loop always has slides on both sides.
  const repeat = Math.max(1, Math.ceil(MIN_SLIDES_FOR_LOOP / testimonials.length));
  const slides = Array.from({ length: repeat }).flatMap(() => testimonials);

  return (
    <div className="testimonial-carousel">
      <Swiper
        className="testimonial-slider"
        modules={[Navigation]}
        slidesPerView="auto"
        centeredSlides
        grabCursor
        loop
        onSwiper={(swiper) => {
          swiperRef.current = swiper;
          updateLayers(swiper);
        }}
        onSlideChange={updateLayers}
        onLoopFix={updateLayers}
        onTransitionEnd={updateLayers}
        onResize={updateLayers}
        onRealIndexChange={(swiper) =>
          setActiveIndex(swiper.realIndex % testimonials.length)
        }
      >
        {slides.map((testimonial, i) => (
          <SwiperSlide key={`${testimonial.id}-${i}`} className="slide">
            <TestimonialBox testimonial={testimonial} />
          </SwiperSlide>
        ))}
      </Swiper>

      {/* Custom pagination: one dot per unique testimonial, decoupled from the
          repeated slide set so duplicates never add extra dots. */}
      <div className="testimonial-pagination" role="tablist" aria-label="Testimonials">
        {testimonials.map((testimonial, i) => (
          <button
            key={testimonial.id}
            type="button"
            role="tab"
            aria-selected={i === activeIndex}
            aria-label={`Show testimonial ${i + 1} of ${testimonials.length}`}
            className={`testimonial-bullet${i === activeIndex ? " is-active" : ""}`}
            onClick={() => swiperRef.current?.slideToLoop(i)}
          />
        ))}
      </div>
    </div>
  );
}

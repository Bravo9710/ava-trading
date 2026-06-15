"use client";

import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Pagination } from "swiper/modules";
import type { Swiper as SwiperType } from "swiper";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import TestimonialBox from "./TestimonialBox";
import type { Testimonial } from "@/lib/wp";

interface TestimonialsSliderProps {
  testimonials: Testimonial[];
}

/**
 * Tag each slide with its distance from the centered/active one so CSS can
 * layer it (scale + blur + z-index). Distance is taken in the looped DOM order,
 * so it stays correct across the loop boundary.
 *   layer 2 = active · layer 1 = adjacent · layer 0 = outer · "hidden" = beyond ±2
 */
function updateLayers(swiper: SwiperType) {
  const slides = swiper.slides as HTMLElement[];
  const activeIdx = swiper.activeIndex;

  slides.forEach((slide, i) => {
    const dist = i - activeIdx; // negative = left of active, positive = right
    const absDist = Math.abs(dist);
    slide.dataset.side = dist < 0 ? "left" : "right";

    if (absDist === 0) {
      slide.dataset.layer = "2";
      delete slide.dataset.side;
    } else if (absDist === 1) {
      slide.dataset.layer = "1";
    } else if (absDist === 2) {
      slide.dataset.layer = "0";
    } else {
      slide.dataset.layer = "hidden";
    }
  });
}

export default function TestimonialsSlider({ testimonials }: TestimonialsSliderProps) {
  return (
    <Swiper
      className="testimonial-slider"
      modules={[Navigation, Pagination]}
      slidesPerView="auto"
      centeredSlides
      grabCursor
      loop
      pagination={{ clickable: true }}
      onSwiper={updateLayers}
      onSlideChange={updateLayers}
      onResize={updateLayers}
    >
      {testimonials.map((testimonial) => (
        <SwiperSlide key={testimonial.id} className="slide">
          <TestimonialBox testimonial={testimonial} />
        </SwiperSlide>
      ))}
    </Swiper>
  );
}

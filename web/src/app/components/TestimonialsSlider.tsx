"use client";

import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Pagination } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import TestimonialBox from "./TestimonialBox";
import type { Testimonial } from "@/lib/wp";

interface TestimonialsSliderProps {
  testimonials: Testimonial[];
}

export default function TestimonialsSlider({ testimonials }: TestimonialsSliderProps) {
  return (
    <Swiper
      className="testimonial-slider"
      modules={[Navigation, Pagination]}
      slidesPerView={5}
      centeredSlides
      spaceBetween={24}
      grabCursor
      loop
      pagination={{ clickable: true }}
    >
      {testimonials.map((testimonial) => (
        <SwiperSlide key={testimonial.id} className="slide">
          <TestimonialBox testimonial={testimonial} />
        </SwiperSlide>
      ))}
    </Swiper>
  );
}

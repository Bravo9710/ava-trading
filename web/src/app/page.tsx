"use client";

import { Swiper, SwiperSlide } from "swiper/react";
import "swiper/css";
import styles from "./page.module.css";
import TestimonialBox from "./components/TestimonialBox";

export default function Home() {
  return (
    <div className={styles.page}>
      <main className={styles.main}>
        <section className={styles.sliderSection}>
          <h2 className={styles.title}>We Let Our Clients Do The Talking</h2>
          <Swiper>
            {Array.from({ length: 6 }).map((_, index) => (
              <SwiperSlide key={index} className={styles.slide}>
                <TestimonialBox />
              </SwiperSlide>
            ))}
          </Swiper>
        </section>
      </main>
    </div>
  );
}

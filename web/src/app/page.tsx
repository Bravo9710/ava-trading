import TestimonialsSlider from "./components/TestimonialsSlider";
import { getTestimonials } from "@/lib/wp";

export default async function Home() {
  const testimonials = await getTestimonials();

  return (
    <div className="page">
      <main className="main">
        <section className="slider-section">
          <h2 className="section-title">We Let Our Clients Do The Talking</h2>
          <TestimonialsSlider testimonials={testimonials} />
        </section>
      </main>
    </div>
  );
}

import Image from "next/image";
import styles from "./page.module.css";

export default function Home() {
  return (
    <div className={styles.page}>
      <main className={styles.main}>
        <section className={styles.sliderSection}>
          <h2 className={styles.title}>We Let Our Clients Do The Talking</h2>
          <div className={styles.slide}>
            <Image src={"/quotes-icon.png"} alt="quotes icon" width={25} height={24} loading="eager" />
          </div>
        </section>
      </main>
    </div>
  );
}

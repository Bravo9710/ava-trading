import styles from "./page.module.css";
import Image from "next/image";

export default function TestimonialBox() {
  return <div>
    <Image src={"/quotes-icon.png"} alt="quotes icon" width={25} height={24} loading="eager" />
  </div>;
}

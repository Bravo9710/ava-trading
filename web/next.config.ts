import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    dangerouslyAllowLocalIP: true,
    remotePatterns: [
      {
        protocol: "http",
        hostname: "ava-trading-testimonials.local",
        pathname: "/wp-content/uploads/**",
      },
    ],
  },
};

export default nextConfig;

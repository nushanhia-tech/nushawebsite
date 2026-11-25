export default {
  serverExternalPackages: ["@php-wasm/node-8-3", "@php-wasm/universal"],
  outputFileTracingIncludes: {
    "/[legacy]": [
      "./legacy/*.php",
      "./node_modules/@php-wasm/node-8-3/{jspi,asyncify}/*.{js,wasm}",
    ],
  },
  async headers() {
    return ["/vendor/:path*", "/bg-image.webp"].map((source) => ({
      source,
      headers: [{ key: "Cache-Control", value: "public, max-age=86400" }],
    }));
  },
  async rewrites() {
    return [{ source: "/", destination: "/index.php" }];
  },
  async redirects() {
    return [
      ["/student-dashboard.php", "student"],
      ["/wing-dashboard.php", "wing"],
      ["/portal.php", "class"],
      ["/admin-dashboard.php", "admin"],
    ].map(([source, role]) => ({
      source,
      destination: "/portals?role=" + role,
      permanent: false,
    }));
  },
};

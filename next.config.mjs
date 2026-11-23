export default {
  serverExternalPackages: ["@php-wasm/node-8-3", "@php-wasm/universal"],
  outputFileTracingIncludes: {
    "/[legacy]": [
      "./legacy/*.php",
      "./node_modules/@php-wasm/node-8-3/{jspi,asyncify}/*.{js,wasm}",
    ],
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

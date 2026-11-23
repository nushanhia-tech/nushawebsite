import "./style.css";

export const metadata = {
  title: "NUS’HA | Union Workspace",
  description: "Noorul Hidaya student union portals",
};

export default function Layout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}

import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Painel",
};

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return <div className="flex flex-1 flex-col">{children}</div>;
}
